<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\MangaUpdates;
use App\Mangas;
use App\Chapters;
use App\Pages;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Jobs\InsertUpdates;

class Api extends Controller
{
    
	# --- Method description --- #
	#
    # Receives a JSON list of mangas and inserts them into the
    # database. It is meant to be the method used to feed the database
    # with mangas.
	function insertMangas(Request $request){

		# Checks if list was sent. If false, returns error message.
		if($request->has('mangaList')){

			# Decodes list
			$mangaList = json_decode($request->mangaList);
			
			# Mangas that have invalid data and can't be processed.
			$failedMangas = [];

			# Mangas that have valid data and can be processed.
			$validMangas = [];

			# Valid mangas that somehow could not be inserted in 
			# database.
			$rejectedMangas = [];

			# Variable that keeps the final status of the method
			# execution.
			$status = 0;

			# Bool variable that checks if there's any invalid manga.
			$dataFailure = false;

			# Bool variable that checks if the database rejected any
			# well valid manga.
			$serverFailure = false;

			# If 'mangaList' is null, then the JSON is invalid.
			if($mangaList == null){

				$status = 4;
				return response()->json(['status' => $status]);

			}

			# Iterates through all mangas to validade every field
			foreach($mangaList as $manga){

				# Link to the cover image
				$coverLink = $manga->CoverLink;

				# Manga name
				$name = $manga->Name;

				# Author name
				$author = $manga->Author;

				# Manga description
				$description = $manga->Description;

				# Manga's validation conditions.
				$condition = 
				strlen($coverLink) <= 100 &&
				strlen($name) <= 60 &&
				strlen($author) <= 60 &&
				strlen($description) <= 800 &&
				strlen($coverLink) > 0 &&
				strlen($name) > 0 &&
				strlen($author) > 0 &&
				strlen($description) > 0;

				# Sets failure variable if conditions aren't met.
				if(!$condition){
					$failed = true;
					$failedMangas[] = $manga;
				}else{
					$validMangas[] = $manga;
				}

			}

			# Array that stores the names of all valid mangas.
			# It'll be used to check if all valid mangas are unique.
			$validNamesList = [];

			# Fills 'validNamesList' with each name in 'validMangas'.
			foreach($validMangas as $manga){

				$validNamesList[] = $manga->Name;

			}

			# Checks if there are any non-unique mangas being
			# inserted. If there are, they will be excluded from
			# 'validMangas' and inserted in 'failedMangas'.
			# If the select fails, we cannot be sure if further
			# insertion failures will be due to server or data error.
			# So, we deem the request as invalid and return.
			try{

				$results = Mangas::select('Name')
				->whereIn('Name',$validNamesList)
				->get()->toArray();

				# If query executes, then we get the results.
				$nonUnique = [];

				foreach($results as $res){

					$nonUnique[] = $res['Name'];

				}

			}catch(QueryException $e){

				# The request is invalid.

				$status = 2;
				return response()->json([
					'status' => $status,
					'rejectedMangas' => $validMangas
				]);

			}

			# If we got any results from the previous query, it
			# means that some valid mangas are already in the database
			# which means the user it trying to insert it again, which
			# is against the business logic. Thus, each non-unique
			# manga in 'validMangas' will be removed and inserted into
			# 'failedMangas', since it's data is invalid from the
			# business logic point of view.
			foreach($validMangas as $manga){

				# Checks if manga name is non unique
				if(in_array($manga->Name,$nonUnique) != false){

					# If it is, remove it from valid mangas and insert
					# into failedMangas.

					$index = array_search($manga,$validMangas);

					if($index !== false){

						$failedMangas[] = $manga;
						$dataFailure = true;
						unset($validMangas[$index]);

					}

				}	
			
			}

			# Now, let's try to insert the real valid mangas
			# into the database.
			foreach($validMangas as $manga){

				# Sets a default timezone
				date_default_timezone_set("America/Sao_Paulo");

				# Create new Manga object and sets its attributes.
				$newManga = new Mangas();
				$newManga->CoverLink = $manga->CoverLink;
				$newManga->Name = $manga->Name;
				$newManga->Author = $manga->Author;
				$newManga->Description = $manga->Description;
				$newManga->UpdatedAt = date("Y-m-d H:i:s");

				# If manga can't be saved, add manga to the 
				# `rejectedMangas` array, because the server rejected
				# a perfectly fine manga for some reason (connection
				# rejected, network timeout, etc.), and it's not a 
				# data correctness problem.

				# At this point, we know that all valid mangas are
				# valid and non-unique, which means that they should 
				# be able to be inserted. If the insertion fails, we
				# know that it can only be a server problem. So, we 
				# add any failing manga to rejected mangas.
				try{

					$newManga->save();

				}catch(QueryException $e){

					dd($e);

					# A server failure occured
					$rejectedMangas[] = $manga;
					$serverFailure = true;

				}

			}

			# Now we check what kind of errors have ocurred until now
			# and return a status code accordingly.
			if($serverFailure && $dataFailure){

				# Request presented both server and data errors.
				$status = 3;
				return response()->json([
					'status' => $status,
					'failedMangas' => $failedMangas,
					'rejectedMangas' => $rejectedMangas
				]);

			}else if($serverFailure && !$dataFailure){

				# Request presented only server errors.
				$status = 2;
				return response()->json([
					'status' => $status,
					'rejectedMangas' => $rejectedMangas
				]);

			}else if(!$serverFailure && $dataFailure){

				# Request presented only data errors.
				$status = 1;
				return response()->json([
					'status' => $status,
					'failedMangas' => $failedMangas
				]);

			}else{

				# Request didn't present any errors.
				$status = 0;
				return response()->json(['status' => $status]);

			}


		}else{

			# Request is invalid.
			$status = 4;
			return response()->json([
				"status" => $status
			]);

		}

	}

	# --- Method description --- #
	#
    # Receives a JSON list of manga's chapters and pages and inserts
    # them into the database. It is meant to be the method used to 
    # feed the database with new chapters.
	function insertChapters(Request $request){

		# Check if chapter list was sent.
		if($request->has('chapterList')){

			# decodes the JSON sent through request.
			$chapterList = json_decode($request->chapterList);

			# Checks if any data failure occured.
			$dataFailure = false;
			# Checks if any server failure occured.
			$serverFailure = false;
			# Checks if any page has failed or been rejected.
			$didAnyPageFail = false;

			# Keeps all chapters that have invalid data.
			$failedChapters = [];
			# Keeps all chapters that are valid but were rejected.
			$rejectedChapters = [];
			# Keeps all chapters that are valid and were accepted.
			$validChapters = [];
			# Keeps all pages that have invalid data;
			$failedPages = [];
			# Keeps all pages that have valid data but were rejected.
			$rejectedPages = [];
			# Keeps all pages that are valid and were accepted.
			$validPages = [];
			# Keeps the status code of the result.
			$status = 0;

			# List that keeps all chapter updates to be inserted.
			$updatesList = [];

			# If 'chapterList' is null, then JSON is invalid.
			if($chapterList == null){
				$status = 4;
				return response()->json(['status' => $status]);
			}

			# Iterate through each chapter, and iterate through each 
			# page of the chapter. Inserts any invalid chapter or 
			# page in one of the previous array.

			foreach($chapterList as $chapter){

				# Attributes of the chapter.
				$pagesList = $chapter->pagesList;
				$mangaName = $chapter->MangaName;
				$name = $chapter->Name;
				$number = $chapter->Number;
				$pageCount = $chapter->PageCount;

				# Bool variable which groups the necessary conditions
				# for a chapter to be accepter as valid.
				$chapterCondition = 

				is_numeric($number) &&
				count($pagesList) > 0 &&
				strlen($mangaName) > 0 &&
				strlen($mangaName) <= 60 &&
				strlen($name) <= 60 &&
				strlen($name) > 0 &&
				is_numeric($pageCount);
				$pageCount > 0;

				# If condition has failed, add chapter to failed 
				# chapters' list.
				if(!$chapterCondition){

					$dataFailure = true;
					$failedChapters[] = $chapter;

				}else{

					$validChapters[] = $chapter;

				}

			}

			# Array with information of valid chapters.
			$validChapterData = [
				'MangaNames' => [],
				'Numbers' => []
			];

			# Creates arrays with columns needed to check if any 
			# chapter in valid mangas has already been inserted in the
			# database.
			foreach($validChapters as $validChapter){

				$validChapterData['MangaNames'][] = 
				$validChapter->MangaName;

				$validChapterData['Numbers'][] = 
				$validChapter->Number;
			
			}

			# Array that will keep all non-unique chapters.
			$nonUnique = [];

			# Selects all chapters which have the same MangaName and
			# Number as the chapters from validChapters. If the query 
			# fails, then a failure status will be returned.
			try{

				$results = Chapters::select('Number')
				->whereIn('MangaName',$validChapterData['MangaNames'])
				->whereIn('Number',$validChapterData['Numbers'])
				->get()
				->toArray();

				foreach($results as $res){

					$nonUnique[] = $res['Number'];

				}

			}catch(QueryException $e){

				if($dataFailure && !$serverFailure){

					$status = 1;
					return response()->json([
						'status' => $status,
						'failedChapters' => $failedChapters
					]);

				}else if(!$dataFailure && $serverFailure){

					$status = 2;
					return response()->json([
						'status' => $status,
						'rejectedChapters' => $validChapters
					]);

				}else{

					$status = 3;
					return response()->json([
						'status' => $status,
						'rejectedChapters' => $validChapters,
						'failedChapters' => $failedChapters
					]);

				}

			}

			# If any valid chapters is already inserted in database,
			# remove the chapter from 'validChapters' and put it in
			# 'failedChapters'.
			foreach($validChapters as $chapter){

				if(in_array($chapter->Number,$nonUnique) != false){

					$index = array_search($chapter,$validChapters);

					if($index !== false){

						$failedChapters[] = $chapter;
						$dataFailure = true;
						unset($validChapters[$index]);

					}

				}

			}

			# Iterates through the real valid chapters to 
			# validade their pages.
			foreach($validChapters as $validChapter){

				# Begins transaction so that queries may be rolled
				# back in case of error.
				DB::beginTransaction();

				$newChapter = new Chapters();

				$newChapter->Number = $validChapter->Number;
				$newChapter->MangaName = $validChapter->MangaName;
				$newChapter->Name = $validChapter->Name;
				$newChapter->PageCount = $validChapter->PageCount;
				$newChapter->PublishedAt = date("Y-m-d H:i:s");

				# If chapter cannot be inserted, then we know that's 
				# due to a server error, because all valid chapters
				# are valid and unique. 
				try{

					if($newChapter->save()){

						# Keeps count of the current page.
						$pageCounter = 0;

						# Iterates through pages list.
						foreach($validChapter->pagesList as $page){


							# Page attributes.
							$pageLink = $page->PageLink;
							$number = $page->Number;
							$mangaName = $newChapter->MangaName;
							$chapterNumber = $newChapter->Number;
							
							# Bool variable that checks if page is
							# valid.
							$pageConditions = 

							strlen($mangaName) > 0 &&
							strlen($mangaName) <= 60 &&
							strlen($pageLink) > 0 &&
							is_numeric($number) &&
							is_numeric($chapterNumber);


							# If conditions aren't met, add page to
							# failed pages list.
							if(!$pageConditions){

								$failedPages[] = [$validChapter->MangaName." -> ".$validChapter->Name => $pageCounter];
								$dataFailure = true;
								$didAnyPageFail = true;

							}else{

								$newPage = new Pages();
								$newPage->Number = $number;
								$newPage->ChapterNumber = $newChapter->Number;
								$newPage->MangaName = $newChapter->MangaName;
								$newPage->PageLink = $pageLink;

								# If page can't be saved, add it to
								# failed pages list.
								try{

									$newPage->save();
								
								}catch(QueryException $e){

									$rejectedPages[] = [$validChapter->MangaName.":".$validChapter->Name => $page];
									$serverFailure = true;
									$didAnyPageFail = true;

								}

							}

							# Now let's go to the next page.
							$pageCounter += 1;

						}

						# If any page couldn't be inserted, then the 
						# chapter as a whole is incomplete and should
						# be discarded. If all pages are inserted, 
						# then add chapter to the updates list.
						if($didAnyPageFail){

							# If any page insertion failed, then
							# rollback all queries.
							DB::rollback();

						}else{

							DB::commit();

							# Increments count of updated chapters of
							# a specific manga.
							if(isset($updatesList[$newChapter->MangaName])){

								$updatesList[$newChapter->MangaName]['ChapterCount'] += 1;
								$updatesList[$newChapter->MangaName]['UpdatedAt'] = $validChapter->UpdatedAt;

							}else{

								$updatesList[$newChapter->MangaName] = [
									'UpdatedAt' => $newChapter->UpdatedAt,
									'MangaName' => $newChapter->MangaName,
									'ChapterCount' => 1
								];

							}

						}

					}

				}catch(QueryException $e){

					# As the chapter could not be saved, it means
					# that the server rejected it. So, its pages don't
					# need to be checked, since they won't belong to
					# a chapter.

					$serverFailure = true;
					$rejectedChapters[] = $validChapter;
					continue;

				}

			}

			# Dispatches a job that will make sure that all 
			# updates will be inserted.
			# Tries 3 times to avoid execution loop.
			InsertUpdates::dispatch($updatesList,3);

			# Now, we check what kinds of erros happened and we return
			# the appropriate status code and response data.
			if($serverFailure && $dataFailure){

				$status = 3;
				return response()->json([
					'status' => $status,
					'rejectedChapters' => $rejectedChapters,
					'failedChapters' => $failedChapters,
					'failedPages' => $failedPages
				]);

			}else if(!$serverFailure && $dataFailure){

				$status = 1;
				return response()->json([
					'status' => $status,
					'failedChapters' => $failedChapters,
					'failedPages' => $failedPages
				]);

			}else if(!$dataFailure && $serverFailure){

				$status = 2;
				return response()->json([
					'status' => $status,
					'rejectedChapters' => $rejectedChapters,
				]);

			}else{

				$status = 0;
				return response()->json([
					'status' => $status,
				]);

			}

		}else{

			# The request is invalid.
			$status = 4;
			return response()->json(['status' => $status]);

		}

	}

	# --- Method description --- #
	#
	# Returns the number of updated chapters, if there are any,
	# for a given list of favorites.
	function checkUpdates(Request $request){

		# Checks if favorites list was sent.
		if($request->has('favoritesList')){

			# Decodes JSON data.
			$favoritesList = json_decode($request->favoritesList);

			# Stores favorites with invalid data.
			$failedFavorites = [];

			# Stores favorites with valid data.
			$validFavorites = [];

			# Stores valid sfavorites whose query failed.
			$rejectedFavorites = [];

			# List with updates of each favorite manga;
			$updatesList = [];

			# Checks if data failure happened.
			$dataFailure = false;

			# Checks if server failure happened.
			$serverFailure = false;

			# Stores request status.
			$status = 0;

			# If 'favoritesList' is null, then JSON is invalid.
			if($favoritesList == null){

				$status = 4;
				return response()->json(['status' => $status]);

			}

			# Iterates through favorites checking if they are valid.
			foreach($favoritesList as $favorite){

				# Condition that checks if favorite is valid. 
				
				$condition = 

				isset($favorite->Name) &&
				isset($favorite->UpdatedAt) &&
				strtotime($favorite->UpdatedAt) &&
				strlen($favorite->Name) > 0 &&
				strlen($favorite->Name) <= 60;

				# If condition fails, add favorite to list of failed.
				# Else, add to the list of valids.
				if(!$condition){
					$dataFailure = true;
					$failed = true;
					$failedFavorites[] = $favorite;
				}else{
					$validFavorites[] = $favorite;
				}

			}

			# Iterate through all valid favorites and queries each one
			# of them for their updated chapter count from some point at
			# time ultil present date. If query fails, then it means
			# that a server failure occured.
			foreach($validFavorites as $validFavorite){
				try{

					$updatedChapters = MangaUpdates()::selectRaw('count("ChapterCount")')->where(
						['UpdatedAt','>',$validFavorite->UpdatedAt],
						['MangaName','=',$validFavorite->Name]
					)->get();

					# Since query didn't fail, add the chapter count to
					# the updates list.
					$updatesList[$validFavorite->Name] = $updatedChapter;

				}catch(QueryException $e){

					# As server failed, set server failure status to
					# true and add favorite to rejected list.
					$serverFailure = true;
					$rejectedFavorites[] = $validFavorite;

				}
			}

			# This if-else block returns status message with updates array. 
			# If any favorites have failed, put them in a list of failed 
			# favorites and return it in the response.

			if($dataFailure && $serverFailure){

				# Data and server failures occurred.
				$status = 3;
				return response()->json([
					'status' => $status,
					'failedFavorites' => $failedFavorites,
					'rejectedFavorites' => $rejectedFavorites
				]);

			}else if($dataFailure && !$serverFailure){

				# Only data failure occurred.
				$status = 1;
				return response()->json([
					'status' => $status,
					'failedFavorites' => $failedFavorites
				]);

			}else if(!$dataFailure && $serverFailure){

				# Only server failure occurred.
				$status = 2;
				return response()->json([
					'status' => $status,
					'rejectedFavorites' => $rejectedFavorites
				]);

			}else{

				# No failure occurred.
				$status = 0;
				return response()->json([
					'status' => $status
				]);

			}

		}else{

			# Request is invalid.
			$status = 4;
			return response()->json(['status' => $status]);

		}

	}

	# --- Method description --- #
	#
	# Returns the last 90 updates made. If an offset is sent, then
	# get the last 90 updates immediately prior to the given date.
	function getUpdates(Request $request){

		if($request->has('offset')){

			$offset = $request->offset;

			# Checks if the query will throw any exception.
			try{
				
				$updates = MangaUpdates::
				select('ChapterCount','MangaName','UpdatedAt')
				->where('id','<',$offset)
				->limit(90)
				->get();

				$status = 0;

				return response()->json([
					'status' => $status,
					'updates' => $updates->toJson()
				]);
			
			}catch(QueryException $e){
				
				# If query fails, it can  only be a server failure.
				$status = 2;
				return response()->json(['status' => $status]);
			
			}

		}else{

			# Gets the 90 last inserted updates and returns them to
			# user. Checks if the query will throw any exception.
			try{
			
				$updates = MangaUpdates::select('ChapterCount','MangaName','UpdatedAt')
				->take(90)
				->get();

				$status = 0;
				return response()->json(['status' => $status, 'updates' => $updates]);

			}catch(QueryException $e){

				# If query fails, it can only be a server failure.
				$status = 2;
				return response()->json(['status' => $status]);
			}

		}


	}

	# --- Method description --- #
	#
	# Returns the base manga list, which is meant to be stored inside
	# the app. This method is not meant to be called frequently.
	# If an offset is given, then it will return the 500 mangas where
	# the id < offset.
	function getMangaList(Request $request){

		if($request->has('offset')){

			$offset = $request->offset;

			# If offset isn't numeric, the request is invalid.
			if(!is_numeric($offset)){

				$status = 4;
				return reponse()->json([
					'status' => $status
				]);

			}

			# Checks if the query will throw any exception.
			try{

				$mangas = Mangas::
				select('id','Name','CoverLink')
				->where('id','>',$offset)
				->limit(500)
				->get();

				return response()->json($mangas);

			}catch(QueryException $e){

				# If query fails, it can only be a server failure.
				$status = 2;
				return response()->json(['status' => $status]);

			}

		}else{

			# Gets the first 9000 mangas and return it to the user.
			# Checks if the query will throw any exception.
			try{

				$mangas = Mangas::select('id','Name','CoverLink')
				->limit(9000)
				->get();
				
				$status = 0;

				return response()->json([
					'status' => $status,
					'mangas' => $mangas
				]);
			
			}catch(QueryException $e){

				# If query failed, it can only be a server failure.
				$status = 2;
				return response()->json(['status' => $status]);

			}

		}

	}

	# --- Method description --- #
	#
	# Receives a string with the name of the manga and makes a search
	# with every word contained in it, returning all mangas that have
	# a word match.
	function search(Request $request){

		# Checks if query string was sent.
		if($request->has('searchString')){

			# checks if string is valid.
			$stringConditions = 
			
			strlen($request->searchString) > 0;


			# if conditions aren't met, the request is invalid.
			if(!$stringConditions){

				$status = 4;
				return response()->json(['status' => $status]);

			}else{

				# Prepares search string and splits it in words.
				$searchString = trim($request->searchString);
				$words = explode(" ",$searchString);

				for($i = 0; $i < count($words); $i++){

					$words[$i] = '%'.$words[$i].'%';

				}

				# Constructs the query string by appending 'piece'
				# to 'queryString'.
				$queryString = "\"Name\" ILIKE ?";
				$piece = " OR \"Name\" ILIKE ?";

				for($i = 0; $i < count($words)-1; $i++){

					$queryString .= $piece;

				}

				# List of words are passed and query is made.
				# Only distinct results are fetched. Checks if the 
				# query will throw any exception.
				try{

					$mangas = Mangas::
					select('id','Name','CoverLink')
					->whereRaw($queryString,$words)
					->distinct()
					->get();

					$status = 0;
					return response()
					->json([
						'status' => $status,
						'mangas' => $mangas
					]);
					
				}catch(QueryException $e){

					# If the query failed, it means that a server
					# failure occurred.
					$status = 2;
					return response()->json(['status' => $status]);

				}

			}

		}else{

			# Request is invalid
			$status = 4;
			return response()->json(['status' => $status]);

		}

	}

	# --- Method description --- #
	#
	# Returns a manga object, which consists in an updated manga and
	# it's chapters list. This method is meant to be called when the
	# user clicks in a manga. 
	function getManga(Request $request){


		# If request doesn't have the name of the wanted manga,
		# then it is invalid.
		if($request->has('mangaName')){

			# Selects manga where 'name = mangaName'.
			# Checks is the query will throw any exception.
			try{

				$manga = Mangas::
				select('id','Name','CoverLink','UpdatedAt','Description')
				->where('Name','=',$request->mangaName)->get();

				$status = 0;
			
				return response()->json([
					'status' => $status,
					'manga' => $manga
				]);

			}catch(QueryException $e){

				# If the query failed, it means that a server 
				# failure occurred.
				$status = 2;
				return response()->json(['status' => $status]);

			}

		}else{

			# Request is invalid.
			$status = 4;
			return response()->json(['status' => $status]);

		}

	}

	# --- Method description --- #
	#
	# Returns the page list of a manga chapter.
	# This method is meant to be called when the user clicks in a
	# chapter of a manga's chapter list.
	function getPages(Request $request){

		# Checks if manga and chapter identifiers were sent.
		# Else, the request is invalid.
		if($request->has('mangaName') && $request->has('chapterNumber')){

			# Checks if the query will throw any exception.
			try{

				$pages = Pages::select('','')->where([
					['MangaName','=',$request->mangaName],
					['ChapterNumber','=',$request->chapterNumber]
				])->get();

				$status = 0;

				return response()->json([
					'status' => $status,
					'pages' => $pages
				]);

			}catch(QueryException $e){

				# If query failed, it means that a server failure
				# occurred.
				$status = 2;
				return response()->json(['status' => $status]);

			}

		}else{

			# Request is invalid.
			$status = 4;
			return response()->json(['status' => $status]);

		}


	}

	# --- Method description --- #
	#
	# This method returns the desired format that the user should use
	# when sending requests. The user will often send lists of 
	# elements to be inserted in the database or to be queried. Each 
	# list element should have one of these formats, depending on 
	# which request the user is making.

	function getRequestFormats(){

		$insertMangas = [
			'mangaList' => [
				[
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => 'Manga 1',
					'Author' => 'Author name',
					'Description' => 'Manga description'
				],
				[
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => 'Manga 2',
					'Author' => 'Author name',
					'Description' => 'Manga description'
				]
			]
		]; 

		$insertChapters = [
			'chapterList' => [
				[
					'MangaName' => 'Manga name 1',
					'Name' => 'Chapter name',
					'Number' => 5,
					'PageCount' => 5,
					'pagesList' => [
							[
								'Number' => 1,
								'PageLink' => 'https://link-to-page-1.com'
							],
							[
								'Number' => 2,
								'PageLink' => 'https://link-to-page-2.com'
							]
					]
				],
				[
					'MangaName' => 'Manga name 2',
					'Name' => 'Chapter name',
					'Number' => 12,
					'PageCount' => 5,
					'pagesList' => [
							[
								'Number' => 1,
								'PageLink' => 'https://link-to-page-1.com'
							],
							[
								'Number' => 2,
								'PageLink' => 'https://link-to-page-2.com'
							]
					]
				]
			]
		];

		$checkUpdates = [
			'favoritesList' => [
				[
					'Name' => 'Favorite manga 1',
					'UpdatedAt' => '2020-01-01 00:00:00 -03:00',
				],
				[
					'Name' => 'Favorite manga 2',
					'UpdatedAt' => '2020-01-01 00:00:00 -03:00',
				]
			]
		];

		$getUpdates = [
			'offset' => '2020-01-01 00:00:00 -03:00'
		];

		$getMangaList = [
			'offset' => '2020-01-01 00:00:00 -03:00'
		];

		$search = [
			'searchString' => 'Kimetsu no Yaiba'
		];

		$getManga = [
			'mangaName' => 'Kimetsu no Yaiba'
		];

		$getPages = [
			'mangaName' => 'Kimetsu no Yaiba',
			'chapterNumber' => 5,
		];

		return response()->json([
			'messages' => [
				'insertMangas' => $insertMangas,
				'insertChapters' => $insertChapters,
				'checkUpdates' => $checkUpdates,
				'getUpdates' => $getUpdates,
				'getMangaList' => $getMangaList,
				'search' => $search,
				'getManga' => $getManga,
				'getPages' => $getPages
			]
		]);

	}

	# --- Method description --- #
	#
	# This method returns the expected schema for JSON reponses from
	# any method in this API. It's intended to be used to make it 
	# easier to undestand the message formats and thus facilitate
	# integration with any platforms using this. Be aware that some 
	# fields may not be present in the response, to acomplish 
	# thoughput and bandwidth efficiency. It is a developer 
	# responsibility to be aware of this and check every field. More 
	# on this in the API docs.

	function getResponseFormats(){

		# --- STATUS CODE LIST --- 
		# 0 - Method was executed flawlessly
		# 1 - Some data was invalid, due only to invalid data.
		# 2 - Some data was invalid, due only to internal errors.
		# 3 - Some data was invalid, due to the two previous reasons.
		# 4 - The request can't be comprehended, so it's invalid.
		#
		# In a successful request, all the expected results
		# were accomplished flawlessly (inserting data in the
		# database, selecting data from database).
		#
		# An error may be due to invalid data or to server failure. 
		# So, that's why there are 3 different codes (1,2,3) to
		# represent all possible situations.
		#
		# The invalid request is missing some crucial data,
		# and thus can't be undestood.s
		
		$insertMangas = [
			'status' => 0,
			'failedMangas' => [
				[
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => ',Manga name',
					'Author' => 'Author name',
					'Description' => 'Manga description'
				]
			],
			'rejectedMangas' => [
				[
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => ',Manga name',
					'Author' => 'Author name',
					'Description' => 'Manga description'
				]
			]
		];

		$insertChapters = [
			'status' => 0,
			'failedChapters' => [
				[
					'Number' => 1,
					'MangaName' => 'Manga name',
					'PageCount' => 20,
					'PublishedAt' => '2020-01-01 00:00:00 -03:00'
				]
			],
			'rejectedChapters' => [
				[
					'Number' => 1,
					'MangaName' => 'Manga name',
					'PageCount' => 20,
					'PublishedAt' => '2020-01-01 00:00:00 -03:00'
				]
			],
			'failedPages' => [
				[
					'PageLink' => 'https://link-to-page.com',
					'Number' => 1
				]
			],
			'rejectedPages' => [
				[
					'PageLink' => 'https://link-to-page.com',
					'Number' => 1
				]
			]
		];

		$checkUpdates = [
			'status' => 0,
			'failedFavorites' => [
				[
					'Name' => 'Favorite\'s name',
					'UpdatedAt' => '2020-01-01 00:00:00 -03:00'
				]
			],
			'rejectedFavorites' => [
				[
					'Name' => 'Favorite\'s name',
					'UpdatedAt' => '2020-01-01 00:00:00 -03:00'
				]
			]
		];

		$getUpdates = [
			'status' => 0,
			'updates' => [
				[
					'ChapterCount' => 5,
					'MangaName' => 'Manga name',
					'UpdatedAt' => '2020-01-01 00:00:00 -03:00'
				]
			]
		];

		$getMangaList = [
			'status' => 0,
			'mangaList' => [
				[
					'id' => 1,
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => 'Manga name'
				],
				[
					'id' => 1,
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => 'Manga name'
				]
			]
		];

		$search = [
			'status' => 0,
			'mangas' => [
				[
					'id' => 1,
					'Name' => 'Manga name',
					'CoverLink' => 'https://link-to-cover.com'
				],
				[
					'id' => 1,
					'Name' => 'Manga name',
					'CoverLink' => 'https://link-to-cover.com'
				]
			]
		];

		$getManga = [
			'status' => 0,
			'manga' => [
					'CoverLink' => 'https://link-to-cover.com',
					'Name' => 'Manga name',
					'Author' => 'Author name',
					'Description' => 'Manga description',
					'UpdatedAt' => '2020-01-01 00:00:00 -03:00'
			]
		];

		$getPages = [
			'status' => 0,
			'pages' => [
				[
					'Number' => 1,
					'PageLink' => 'https://link-to-page.com'
				],
				[
					'Number' => 1,
					'PageLink' => 'https://link-to-page.com'
				]
			]
		];

		return response()->json([
			'messages' => [
				'insertMangas' => $insertMangas,
				'insertChapters' => $insertChapters,
				'checkUpdates' => $checkUpdates,
				'getUpdates' => $getUpdates,
				'getMangaList' => $getMangaList,
				'search' => $search,
				'getManga' => $getManga,
				'getPages' => $getPages
			]
		]);

	}

}
