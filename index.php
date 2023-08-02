<?php

declare(strict_types = 1);

try {
    $postmanUrl  = 'https://api.getpostman.com';
    $workspaceId = 'YOUR_WORKSPACE_ID';
    $apiKey      = 'YOUR_POSTMAN_APIKEY';
    $env         = 'production';

    $curl = curl_init();

    $curlOptions = [
        CURLOPT_URL            => $postmanUrl . '/workspaces/' . $workspaceId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => [
            'X-Api-Key: ' . $apiKey,
        ]
    ];

    // If you want to disable SSL verification. In this case, I disabled for local environment
    if ($env != 'production') {
        $curlOptions[CURLOPT_SSL_VERIFYPEER] = 0;
    }

    curl_setopt_array($curl, $curlOptions);

    $jsonResponse = curl_exec($curl);
    curl_close($curl);

    if (curl_error($curl)) {
        throw new Exception(curl_error($curl));
    }
    $response = json_decode($jsonResponse);

    $mcurl    = curl_multi_init();
    $curlList = [];
    foreach ($response->workspace->collections as $item) {
        $curlList[$item->uid]     = curl_init($postmanUrl . '/collections/' . $item->uid);
        $curlOptions[CURLOPT_URL] = $postmanUrl . '/collections/' . $item->uid;
        curl_setopt_array($curlList[$item->uid], $curlOptions);

        curl_multi_add_handle($mcurl, $curlList[$item->uid]);
    }
    foreach ($response->workspace->environments as $item) {
        $curlList[$item->uid]     = curl_init($postmanUrl . '/environments/' . $item->uid);
        $curlOptions[CURLOPT_URL] = $postmanUrl . '/environments/' . $item->uid;
        curl_setopt_array($curlList[$item->uid], $curlOptions);

        curl_multi_add_handle($mcurl, $curlList[$item->uid]);
    }

    $running = null;
    do {
        curl_multi_exec($mcurl, $running);
    } while ($running);

    foreach (array_keys($curlList) as $key) {
        if (curl_error($curlList[$key])) {
            throw new Exception(curl_error($curlList[$key]));
        }
        $jsonResponse = curl_multi_getcontent($curlList[$key]);

        $response = json_decode($jsonResponse);
        if (property_exists($response, 'collection')) {
            file_put_contents(
                'postman/collections/' . $response->collection->info->_postman_id . '.json',
                json_encode($response->collection)
            );
        }
        if (property_exists($response, 'environment')) {
            file_put_contents(
                'postman/environments/' . $response->environment->id . '.json',
                json_encode($response->environment)
            );
        }
        curl_multi_remove_handle($mcurl, $curlList[$key]);
    }
    curl_multi_close($mcurl);
} catch (Exception $e) {
    file_put_contents('error_log.log', $e->getMessage() . ' at line ' . $e->getLine());
}
