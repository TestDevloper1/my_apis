<?php


namespace App\Http\Controllers\AppApi;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class DownloaderController extends BaseController
{



public function scrape(Request $request)
{
    $link = urlencode($request->link);

    // URL to scrape
    $url = "https://savevideo.to/api/ajaxSearch?q=$link";

    // Initialize Guzzle client
    $client = new Client();

    try {
        // post request to the URL
        $response = $client->post($url);

        // Get the response body
        $data = $response->getBody();

        // Decode JSON response
        $responseData = json_decode($data, true);

        // Extract HTML content
        $html = $responseData['data'];

        // Preprocess the HTML content to ensure proper encoding
        $html = $this->preprocessHtml($html);

        // Create a DOMDocument object
        $dom = new \DOMDocument();

        // Load HTML content into the DOMDocument
        $dom->loadHTML($html);

        // Find the thumbnail image URL
        $thumbnailUrl = $dom->getElementsByTagName('img')->item(0)->getAttribute('src');

        // Find the video download URL
        $videoUrl = $dom->getElementsByTagName('a')->item(1)->getAttribute('href');

        // Now you can use $thumbnailUrl and $videoUrl as needed
        // For example, you can store them in your database or return them as a response

        $time = (string)round(microtime(true) * 1000);

        return response()->json(['links' => ['thumbnail_url' => $thumbnailUrl, 'video_url' => $videoUrl, 'title' => "Instagram$time",]]);

    } catch (\Exception $e) {
        // Handle any errors, e.g., if the request fails or the response is not in the expected format
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}


private function preprocessHtml($html)
{
    $html = str_replace('&', '&amp;', $html);

    return $html;
}

public function twitterscrape(Request $request)
{
    $link = urlencode($request->link);

    $url = "https://twitsave.com/info?url=$link";

    $client = new Client();

    try {
        $response = $client->get($url);

        $html = $response->getBody()->getContents();

        $html = $this->preprocessHtml($html);


        $dom = new \DOMDocument();

        libxml_use_internal_errors(true);

        $dom->loadHTML($html);

        libxml_clear_errors();

        $videoTags = $dom->getElementsByTagName('video');


        if ($videoTags->length > 0) {

            $videoUrl = $videoTags->item(0)->getAttribute('src');

            $thumbnail = $videoTags->item(0)->getAttribute('poster');

            $time = (string)round(microtime(true) * 1000);

            return response()->json(['links' => ['thumbnail_url' => $thumbnail, 'video_url' => $videoUrl, 'title' => "Twitter$time",]]);

        } else {
            return response()->json(['error' => 'No video found in the response'], 404);
        }
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function facebookscrape(Request $request)
{


    $link = urlencode($request->link);


    $Url = "https://x2download.app/api/ajaxSearch/facebook?q=$link";


    $client = new Client();

    try {

        $response = $client->post($Url);


        $responseBody = $response->getBody()->getContents();


        $responseData = json_decode($responseBody, true);

        if (!isset($responseData['links'])) {
            return response()->json(['error' => 'No links found in the response'], 404);
        }


        $links = $responseData['links'];
        $thumbnailUrl = $responseData['thumbnail'];

        if (empty($links)) {
            return response()->json(['error' => 'No video URLs found'], 404);
        }

        $time = (string)round(microtime(true) * 1000);

        $videoUrl = reset($links);

        return response()->json(['links' => ['thumbnail_url' => $thumbnailUrl, 'video_url' => $videoUrl, 'title' => "Facebook_$time",]]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function youtubescrape(Request $request)
{
    $link = urlencode($request->link);
    $url = "https://api.smoothdownloader.com/get_video_info?url=$link";

    $response = Http::get($url);

    if ($response->successful()) {
        $data = $response->json();

        $audioUrl = $data['audio_url'];
        $thumbnailUrl = $data['thumbnail_url'];
        $title = $data['title'];
        $videoUrl = $data['video_url'];

        return response()->json(['links' => ['thumbnail_url' => $thumbnailUrl, 'video_url' => $videoUrl, 'audio_url' => $audioUrl, 'title' => $title,]]);

    } else {

        $errorMessage = $response->status() . ': ' . $response->body();

        return response()->json(['error' => $errorMessage], $response->status());
    }
}


public function tiktok(Request $request)
{
    $link = $request->link;

    $url = "https://tools.betabotz.eu.org/tools/tiktokdl?url=$link";


    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data['status'] === 200 && isset($data['result']['data'])) {
        $result = $data['result']['data'];

        $vTitle = $result['title'];

        $words = explode(" ", $vTitle);

        $title = implode(" ", array_slice($words, 0, 3));


        $tiktokData = [
            'thumbnail_url' => $result['cover'],
            'video_url' => $result['play'],
            'music' => $result['music'],
            'title' => $title,
            'duration' => $result['duration'],
        ];


        return response()->json(['links' => $tiktokData]);

    }

    return response()->json(['error' => 'Invalid response from the API'], 400);
}








public function test(){
return response()->json(['test' => 'test api']);
}


}


