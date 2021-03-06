<?php

namespace App\Services\V1;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlightsService
{
    public function getFlights()
    {
        $flights = array_merge($this->getOutboundFlights(), $this->getInboundFlights());
        $getGroups = $this->groupFlights();


        $return = [
            'flights' => $flights,
            'groups' => $getGroups['groups'],
            'total_groups' => count($getGroups['groups']),
            'total_flights' => count($flights),
            'cheapestPrice' => $getGroups['cheapestPrice'],
            'cheapestGroup' => $getGroups['cheapestGroup']
        ];
        return $return;
    }

    public function getInboundFlights()
    {
        if (!Cache::has('flights_inbound')) {
            $client = new Client();
            $response = $client->get('http://prova.123milhas.net/api/flights/inbound');
            $response = json_decode((string)$response->getBody(), true);
            Cache::add('flights_inbound', $response, now()->addMinutes(20));
        } else {
            $response = Cache::get('flights_inbound');

        }
        return $response;
    }

    public function getOutboundFlights()
    {
        if (!Cache::has('flights_outbound')) {
            $client = new Client();
            $response = $client->get('http://prova.123milhas.net/api/flights/outbound');
            $response = json_decode((string)$response->getBody(), true);
            Cache::add('flights_outbound', $response, now()->addMinutes(20));
        } else {
            $response = Cache::get('flights_outbound');
        }
        return $response;

    }

    public function groupFlights()
    {
        if(!Cache::has('processed_flights')){
            $groups = $this->processOutbounds();

            $groups = $this->processInbounds($groups);

            usort($groups, function ($a, $b) {
                return $a['totalPrice'] <=> $b['totalPrice'];
            });
            Cache::put('processed_flights',$groups,now()->addMinutes(20));
        }else{
            $groups = Cache::get('processed_flights');
        }
        $output = [];
        foreach ($groups as $item) {
            $output[$item['uniqueId']] = $item['totalPrice'];
        }
        return [
            'groups' => $groups,
            'cheapestPrice' => $output[array_keys($output, min($output))[0]],
            'cheapestGroup' => array_keys($output, min($output))[0]
        ];
    }

    public function processInbounds($groups) //O(n??)
    {
        $inboundFlights = $this->getInboundFlights();
        foreach ($groups as $key => $group) {
            foreach ($inboundFlights as $inboundFlight) {
                if ($group['outbound'][0]['fare'] == $inboundFlight['fare']) {
                    if (count($groups[$key]['inbound']) > 0) {
                        $price = $group['outbound'][0]['price'] + $inboundFlight['price'];
                        if ($price == $groups[$key]['totalPrice']) {
                            $groups[$key]['totalPrice'] = $group['outbound'][0]['price'] + $inboundFlight['price'];
                            array_push($groups[$key]['inbound'], $inboundFlight);
                        }
                    } else {
                        $groups[$key]['totalPrice'] = $group['outbound'][0]['price'] + $inboundFlight['price'];
                        array_push($groups[$key]['inbound'], $inboundFlight);
                    }
                }
            }
        }
        return $groups;
    }

    public function processOutbounds()
    {
        $outboundFlights = $this->getOutboundFlights();
        $groups = [];
        foreach ($outboundFlights as $key => $outboundFlight) {
            $newGroup = [
                'uniqueId' => Str::random(10),
                'totalPrice' => 0,
                'outbound' => [],
                'inbound' => []
            ];
            $newGroup['totalPrice'] = $outboundFlight['price'];
            array_push($newGroup['outbound'], $outboundFlight);
            $groupExists = $this->groupExists($groups, $newGroup);
            if ($groupExists['exists']) {
                array_push($groups[$groupExists['key']]['outbound'], $outboundFlight);
            } else {
                array_push($groups, $newGroup);
            }
        }
        return $groups;
    }

    public function groupExists($groups, $newGroup): array
    {
        foreach ($groups as $key => $group) {
            if ($group['totalPrice'] == $newGroup['totalPrice'] && $group['outbound'][0]['fare'] == $newGroup['outbound'][0]['fare']) {
                return [
                    'exists' => true,
                    'key' => $key
                ];
            }
        }
        return [
            'exists' => false,
            'key' => false
        ];
    }

}
