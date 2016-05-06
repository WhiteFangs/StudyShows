<?php

include('./BotHelpers.php');
include('./StudyWords.php');
require_once('./TwitterAPIExchange.php');

/** Set access tokens here - see: https://apps.twitter.com/ **/
$APIsettings = array(
    'oauth_access_token' => "YOUR_ACCESS_TOKEN",
    'oauth_access_token_secret' => "YOUR_ACCESS_TOKEN_SECRET",
    'consumer_key' => "YOU_CONSUMER_KEY",
    'consumer_secret' => "YOU_CONSUMER_KEY_SECRET"
);

$googleAPIKey = 'YOUR_GOOGLE_API_KEY';
$googleCSEId = 'YOUR_GOOGLE_CSE_ID';


function getRandomPageNumber($query){
	global $googleAPIKey, $googleCSEId;
	$googleQueryUrl = 'https://www.googleapis.com/customsearch/v1?key='. $googleAPIKey .'&cx='. $googleCSEId .'&q=allintitle:'.$query.'&filter=0';
	$googleSearch = getCURLOutput($googleQueryUrl, false);
	$json = json_decode($googleSearch);
	if($json->error)
		exit();
	$totalResults = $json->searchInformation->totalResults;
	if($totalResults == 0)
		return 0;
	$page = ($totalResults > 10) ? min(rand(1, $totalResults - 10), 100) : 1; // Google usually over estimates available results, thus the min with 100
	return $page;
}

function getNewQuery(){
	global $studyWords;
	$word = $studyWords[array_rand($studyWords)];
	return '"study+shows"+' . $word;
}

function tweet(){
	global $googleAPIKey, $googleCSEId, $APIsettings;
	$urls = array();
	$query = getNewQuery();
	$pageNumber = getRandomPageNumber($query);
	if($pageNumber > 0){
		$googleQueryUrl = 'https://www.googleapis.com/customsearch/v1?key='. $googleAPIKey .'&cx='. $googleCSEId .'&start='.$pageNumber.'&q=allintitle:'.$query.'&filter=0';
		$googleSearch = getCURLOutput($googleQueryUrl, false);
		$json = json_decode($googleSearch);
		if($json->error)
			exit();
		if(is_array($json->items)){
			foreach ($json->items as $result){		
				array_push($urls, $result->link);
			}
			do{
				$urlIndex = array_rand($urls);
				$randomUrl = $urls[$urlIndex];
				$html = getCURLOutput($randomUrl, false);
				$doc = new DOMDocument();
				@$doc->loadHTML($html);
				$nodes = $doc->getElementsByTagName('title');
				if($nodes->length>0) { // get page title
					$tempTitle = $nodes->item(0)->nodeValue;
					$tempTitle = splitAndGetLongest($tempTitle, ' - ');
					$tempTitle = splitAndGetLongest($tempTitle, ' – ');
					$tempTitle = splitAndGetLongest($tempTitle, ' | ');
					$tempTitle = splitAndGetLongest($tempTitle, ' (');
					$tempTitle = splitAndGetLongest($tempTitle, '. ');
					$tempTitle = splitAndGetLongest($tempTitle, ' • ');
					$tempTitle = trim($tempTitle);
					if(strpos(mb_strtolower($tempTitle), "study") !== false && strpos(mb_strtolower($tempTitle), "shows") !== false){ // test correct trim of title
						$title = $tempTitle;
					}else{
						array_splice($urls, $urlIndex, 1);
					}
				}else{
					array_splice($urls, $urlIndex, 1);
				}
			}while(!isset($title) && count($urls) > 0);
			if(isset($title)){
				// Post the tweet
				$postfields = array('status' =>  $title);
				$url = "https://api.twitter.com/1.1/statuses/update.json";
				$requestMethod = "POST";
				$twitter = new TwitterAPIExchange($APIsettings);
				echo $twitter->buildOauth($url, $requestMethod)
							  ->setPostfields($postfields)
							  ->performRequest();
							  
			}else{
				tweet();
			}
		}else{
			tweet();
		}
	}else{
		tweet();
	}
}


tweet();


?>