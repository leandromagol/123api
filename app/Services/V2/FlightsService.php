<?php

namespace App\Services\V2;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlightsService
{
    public array $groups = [];

    public function getFlights(): array
    {
        $flights = $this->getApiFlights();
        $this->buildGroups();
        $output = [];
        usort($this->groups, function ($a, $b) {
            return $a['totalPrice'] <=> $b['totalPrice'];
        });
        foreach ($this->groups as $item) {
            $output[$item['uniqueId']] = $item['totalPrice'];
        }
        return [
            'flights' => $flights,
            'groups' => $this->groups,
            'total_groups' => count($this->groups),
            'total_flights' => count($flights),
            'cheapestPrice' => $output[array_keys($output, min($output))[0]],
            'cheapestGroup' => array_keys($output, min($output))[0]
        ];
    }

    public function getApiFlights()
    {
        if (!Cache::has('flights_apí')){
            $client = new Client();
            $response = $client->get('http://prova.123milhas.net/api/flights');
            $response = json_decode((string)$response->getBody(), true);
            Cache::add('flights_apí', $response, now()->addMinutes(20));
        }else{
            $response = Cache::get('flights_apí');
        }


        return $response;
    }
    public function buildGroups()
    {
        if(!Cache::has('processed_flights')){
            $flights = $this->getApiFlights();
            foreach ($flights as $flight) {
                $this->createNewGroup($flight);
            }
            foreach ($this->groups as $group) {
                $verify = $this->verifyGroupIsComplete($group);;
                if (!$verify['complete']) {
                    foreach ($flights as $flight){
                        if ($flight[$verify['erroOn']]){
                            $this->createNewGroup($flight,true);
                        }
                    }
                }
            }
            
           Cache::add('processed_flights', $this->groups, now()->addMinutes(20));
        }else{
            $this->groups = Cache::get('processed_flights');
        }

    }
    public function createNewGroup($flight,$update = false)
    {
        $newGroup = [
            'uniqueId' => Str::random(10),
            'totalPrice' => 0,
            'outbound' => [],
            'inbound' => []
        ];
        $flightType = $flight['outbound'] ? 'outbound' : 'inbound';

        $newGroup['totalPrice'] = $flight['price'];
        array_push($newGroup[$flightType], $flight);
        $groupExists = $this->groupExists($flight);
        if ($groupExists['exists']) {
            if (isset($groupExists['updateValue'])) {
                $this->groups[$groupExists['key']]['totalPrice'] = $groupExists['updateValue'];
            }
            array_push($this->groups[$groupExists['key']][$flightType], $flight);
        } else {
            if (!$update){
                array_push($this->groups, $newGroup);
            }
        }
    }

    public function groupExists($flight): array
    {
        $flightType = $flight['outbound'] ? 'outbound' : 'inbound';
        $noFlightType = !$flight['outbound'] ? 'outbound' : 'inbound';
        foreach ($this->groups as $key => $group) {
            if ($this->groupIsSameFare($group, $flight) && $this->validateFlightInsNotInGroup($flight, $group)) {
                if ((isset($group[$flightType][0]) && $group[$flightType][0]['price'] == $flight['price'])) {
                    return [
                        'exists' => true,
                        'key' => $key
                    ];
                }
                if (!isset($group[$flightType][0])) {
                    return [
                        'exists' => true,
                        'key' => $key,
                        'updateValue' => $group[$noFlightType][0]['price'] + $flight['price'],
                    ];
                }
            }

        }

        return [
            'exists' => false,
            'key' => false
        ];
    }
    public function groupIsSameFare($group, $flight)
    {
        $flightType = $flight['outbound'] ? 'outbound' : 'inbound';
        $noFlightType = !$flight['outbound'] ? 'outbound' : 'inbound';
        if (isset($group[$flightType][0]) && $group[$flightType][0]['fare'] == $flight['fare']) {
            return true;
        }
        if (isset($group[$noFlightType][0]) && $group[$noFlightType][0]['fare'] == $flight['fare']) {
            return true;
        }
        return false;
    }
    public function validateFlightInsNotInGroup($flight, $group)
    {
        $flightType = $flight['outbound'] ? 'outbound' : 'inbound';
        $ids = array_column($group[$flightType], 'id');
        return !in_array($flight['id'], $ids);
    }
    public function verifyGroupIsComplete($group)
    {
        if (count($group['outbound']) > 0 && count($group['inbound']) > 0) {
            return [
                'complete' => true,
                'outbound' => count($group['outbound']) > 0,
                'inbound' => count($group['inbound']) > 0
            ];
        }
        if (!count($group['outbound']) > 0 ){
            $error = 'outbound';
        }
        if (!count($group['inbound']) > 0 ){
            $error = 'inbound';
        }
        return [
            'complete' => false,
            'erroOn'=>$error
        ];
    }

}
