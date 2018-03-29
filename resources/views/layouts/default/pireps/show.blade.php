@extends('app')
@section('title', 'PIREP '.$pirep->ident)

@section('content')
    <div class="row">
        <div class="col-12">
            <h2 class="description">{{ $pirep->ident }}</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-8">
            <h4 class="description">flight info</h4>
            <table class="table table-hover">
                <tr>
                    <td>Status</td>
                    <td>
                        @if($pirep->state === PirepState::PENDING)
                            <div class="badge badge-warning">
                        @elseif($pirep->state === PirepState::ACCEPTED)
                            <div class="badge badge-success">
                        @elseif($pirep->state === PirepState::REJECTED)
                            <div class="badge badge-danger">
                        @else
                            <div class="badge badge-info">
                        @endif

                        {{ PirepState::label($pirep->state) }}</div>

                        <span class="description" style="padding-left: 20px;">
                            source: {{ PirepSource::label($pirep->source) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>Departure/Arrival</td>
                    <td>
                        {{ $pirep->dpt_airport->icao }} - {{ $pirep->dpt_airport->name }}
                        <span class="description">to</span>
                        {{ $pirep->arr_airport->icao }} - {{ $pirep->arr_airport->name }}
                    </td>
                </tr>

                <tr>
                    <td>Flight Type</td>
                    <td>{{ \App\Models\Enums\FlightType::label($pirep->flight_type) }}</td>
                </tr>

                <tr>
                    <td>Flight Time</td>
                    <td>
                        {{ Utils::minutesToTimeString($pirep->flight_time) }}
                    </td>
                </tr>

                <tr>
                    <td>Filed Route</td>
                    <td>
                        {{ $pirep->route }}
                    </td>
                </tr>

                <tr>
                    <td>Notes</td>
                    <td>
                        {{ $pirep->notes }}
                    </td>
                </tr>

                <tr>
                    <td>Filed On</td>
                    <td>
                        {{ show_datetime($pirep->created_at) }}
                    </td>
                </tr>

            </table>
        </div>

        <div class="col-4">
            {{--
                Show the fields that have been entered
            --}}

            @if(count($pirep->fields) > 0)
                <h4 class="description">fields</h4>
                <table class="table table-hover">
                    <thead>
                    <th>Name</th>
                    <th>Value</th>
                    </thead>
                    <tbody>
                    @foreach($pirep->fields as $field)
                        <tr>
                            <td>{{ $field->name }}</td>
                            <td>{{ $field->value }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{--
        Show the fares that have been entered
    --}}

    @if(count($pirep->fares) > 0)
        <div class="row">
            <div class="col-12">
                <h4 class="description">fares</h4>
                <table class="table table-hover">
                    <thead>
                    <th>Class</th>
                    <th>Count</th>
                    </thead>
                    <tbody>
                    @foreach($pirep->fares as $fare)
                        <tr>
                            <td>{{ $fare->fare->name }} ({{ $fare->fare->code }})</td>
                            <td>{{ $fare->count }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @include('pireps.map')

    @if(count($pirep->acars_logs) > 0)
        <br /><br />
        <div class="row clear">
            <div class="col-12">
                <h3 class="description">flight log</h3>
            </div>
            <div class="col-12">
                <table class="table table-hover" id="users-table">
                    <tbody>
                    @foreach($pirep->acars_logs as $log)
                        <tr>
                            <td nowrap="true">{{ show_datetime($log->created_at) }}</td>
                            <td>{{ $log->log }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

