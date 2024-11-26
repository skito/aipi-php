<?php

namespace AIpi\Toolbox;

class OpenMeteo extends \AIpi\Tools\FunctionCall
{
    public $endpointArgs = [
        'current_weather' => 1
    ];

    public function __construct($endpointArgs=[])
    {
        $this->endpointArgs = array_merge($this->endpointArgs, $endpointArgs);
        parent::__construct(
            'OpenMeteo', 
            'Get weather information by decimal degrees (DD) gps coordinates.', 
            [   
                'lat' => 'number',
                'lng' => 'number',
            ], 
            [
                'required' => ['lat', 'lng'],
                'descriptions' => [
                    'lat' => 'Latitude (DD)',
                    'lng' => 'Longitude (DD)'
                ]
            ],
            [$this, 'GetWeatherInfo']   
        );
    }

    public function GetWeatherInfo($args)
    {
        $lat = $args->lat ?? '';
        $lng = $args->lng ?? '';
        $endpoint = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lng&";
        $endpoint .= http_build_query($this->endpointArgs);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string

        // Execute the request and capture the response
        $response = curl_exec($ch);
        $result = null;

        // Check for errors
        if ($response === false) 
        {
            $result = 'cURL Error: ' . curl_error($ch);
        } 
        else 
        {
            // Decode the JSON response
            $result = json_decode($response, true);
        }

        // Close the cURL session
        curl_close($ch);

        return $result;
    }
}
