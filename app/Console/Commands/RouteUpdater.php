<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Contracts\Controller;
use App\Models\Flight;
use App\Services\FlightService;
use App\Services\AirportService;
use App\Repositories\FlightRepository;
use App\Notifications\Channels\Discord\DiscordMessage;
use App\Notifications\Channels\Discord\DiscordWebhook;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class RouteUpdater extends Command implements ShouldQueue
{
    private FlightService $flightSvc;
    private FlightRepository $flightRepo;
    private AirportService $airportSvc;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and Replace Company Routes';

     /**
     * FlightController constructor.
     *
     * @param AirportRepository     $airportRepo
     * @param FlightRepository      $flightRepo
     * @param FlightService         $flightSvc
     */
    public function __construct(
        AirportService $airportSvc,
        FlightRepository $flightRepo,
        FlightService $flightSvc
    ) {
        $this->airportSvc = $airportSvc;
        $this->flightRepo = $flightRepo;
        $this->flightSvc = $flightSvc;
        parent::__construct();
    }
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        \Log::info('Starting Process');
        $response = Http::get(env('AIRLABS_API_URL') . '&api_key=' . env('AIRLABS_API_KEY'));
        if ($response->status() !== 200) {
            \Log::info('Unable to Access API');
            return false;
        }
        $flightsChunk = $response->collect($key = 'response')
            ->unique('flight_number')
            ->map(function($flight) {
                if($flight['cs_airline_iata'] === null) { return $flight; }
            })
            ->filter()
            ->map(function ($flight) {
                return [
                    'flt_num' => $flight['flight_number'],
                    'callsign' => $flight['flight_icao'],
                    'dep_airport' => $flight['dep_icao'],
                    'arr_airport' => $flight['arr_icao'],
                    'dpt_time' => $flight['dep_time'],
                    'arr_time' => $flight['arr_time'],
                    'flight_time' => $flight['duration'],
                    'days_formatted' => $flight['days'],
                    'dep_terminals' => $flight['dep_terminals'],
                    'arr_terminals' => $flight['arr_terminals'],
                    'aircraft_icao' => $flight['aircraft_icao'],
                    'route' => 'NO ROUTE CURRENTLY AVAILABLE'
                ];
            })
            ->chunk(500);
        
            $flightsCreated = 0;
            $flightsUpdated = 0;
        foreach ($flightsChunk as $chunk) {
            foreach ($chunk as $flight) {
                $days_num = ["7","1","2","3","4","5","6"];
                $days_text = ["sun","mon","tue","wed","thu","fri","sat"];
                $airline_id = 1;
                $flight_num = trim($flight['flt_num']);
                $attrs = [
                    'dpt_airport_id'        => $flight['dep_airport'],
                    'arr_airport_id'        => $flight['arr_airport'],
                    'route'                 => '',
                    'distance'              => $this->airportSvc->calculateDistance(
                                                    $flight['dep_airport'],
                                                    $flight['arr_airport']
                                                ),
                    'level'                 => 0,
                    'dpt_time'              => $flight['dpt_time'] ?: '',
                    'arr_time'              => $flight['arr_time'] ?: '',
                    'flight_time'           => $flight['flight_time'] ?: '',
                    'notes'                 => '',
                    'active'                => true,
                    'days'                  => str_replace($days_text, $days_num, implode($flight['days_formatted'])) ?: '',
                    'load_factor_variance'  => 15,
                    'load_factor'           => rand(70,85)

                ];
                try {
                    $w = ['airline_id' => $airline_id, 'flight_number' => $flight_num];

                    if(is_null($flight['dep_terminals'])) { $departureTerm = ''; } else { $departureTerm = implode(" ",array_filter($flight['dep_terminals'])); }
                    if(is_null($flight['arr_terminals'])) { $arrivalTerm = ''; } else { $arrivalTerm = implode(" ",array_filter($flight['arr_terminals'])); }
                    if(is_null($flight['aircraft_icao'])) { $aircraftICAO = ''; } else { $aircraftICAO = $flight['aircraft_icao']; }

                    $customFields = [['name' => 'Departure Terminals', 'value' => $departureTerm],['name' => 'Arrival Terminals', 'value' => $arrivalTerm],['name' =>'Typical Aircraft', 'value' => $aircraftICAO]];
                    $fields = array_merge($w, $attrs);
                    $flightTmp = new Flight($fields);
                    if ($this->flightSvc->isFlightDuplicate($flightTmp)) {
                        $where = [
                            ['id', '<>', $flightTmp->id],
                            'airline_id'    => $flightTmp->airline_id,
                            'flight_number' => $flightTmp->flight_number,
                        ];
                        $update_flights = $this->flightRepo->findWhere($where);
                        $flight = $this->flightRepo->update($fields,$update_flights[0]->id);
                        $this->flightSvc->updateCustomFields($flight, $customFields);
                        $flightsUpdated = $flightsUpdated + 1;
                    } else {
                        $flight = Flight::create($fields);
                        $this->flightSvc->updateCustomFields($flight, $customFields);
                        $flightsCreated = $flightsCreated + 1;
                    }
                } catch (\Exception $e) {
                    $this->error($e);
                }
            }
        }
        \Log::info('Process Complete schedules created - '.$flightsCreated. ' / Flights Updated - '.$flightsUpdated);
        return;
    }
}
