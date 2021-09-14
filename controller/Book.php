<?php
include 'Ebook.php';

// Firebase ADMIN SDK
use Kreait\Firebase\Factory;

class BookController extends BaseController
{
    

     private function load_book($book_id){

        //The url you wish to send the POST request to
        $url = $this->firebase_functions_endpoint . '/books/get_full_book/'.$book_id;
        
        $result = $this->queryFirestoreGetFunctions($url);

        if ($result) {
            return $result;
        }else{
            return false;
        }
 
    }

    /**
     * "/book/download_epub" Endpoint - Download saved book
     */
    public function download_epubAction(){
        $requestMethod = $_SERVER["REQUEST_METHOD"];
        $arrQueryStringParams = $this->getQueryStringParams();
        if (strtoupper($requestMethod) == 'GET') {
            try {
                
                if (isset($arrQueryStringParams['book_id']) && $arrQueryStringParams['book_id']) {
                    $book_result = $this->load_book($arrQueryStringParams['book_id']);
                    
                    
                    if( $book_result){
                        $myBook = new Ebook();
                        $book = json_decode($book_result);
                        // echo '<pre>';
                        // print_r($book);
                        // echo '</pre>';

                        // TO-DO : Add Cover

                        $myBook->setTitle( addslashes( $book->title ) );
                        foreach($book->chapters as $curr_chapter){
                            if( !isset($curr_chapter->sitename) ){
                                $sitename = "";
                            }else{
                                $sitename =$curr_chapter->sitename;
                            }
                            $subtitle = '<span class="siteName">'.$sitename.'</span> <a href="'.$curr_chapter->url.'">'.$curr_chapter->url.'</a>';
                            $curr_chapter = ['title' => addslashes( $curr_chapter->title ),'subtitle' => $subtitle,'content' => $curr_chapter->clean_content ];
                            $myBook->addChapter($curr_chapter);
                        }
                        $myBook->download_file();
                    }else{
                        return false;
                    }
                }
 
                
            } catch (Error $e) {
                $strErrorDesc = $e->getMessage().'Something went wrong creating ebook! Please contact support.';
                $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
            }
        } else {
            $strErrorDesc = 'Method not supported';
            $strErrorHeader = 'HTTP/1.1 422 Unprocessable Entity';
        }
 
        // send output
        if ($strErrorDesc) {
            $this->sendOutput(json_encode(array('error' => $strErrorDesc)), 
                array('Content-Type: application/json', $strErrorHeader)
            );
        }
    }
   
}