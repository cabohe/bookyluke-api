<?php
class Ebook{

    private $ebook_title = '';
    private $cover_url = '';
    private $ebook_creator = 'BookyLuke';
    private $ebook_lang = 'en-US';
    private $chapters_numeration_length = 4;
    private $filename = '';
    private $chapters = [];

    function __construct($options=[]) {
        if(isset($options["type"])){
            $this->ebook_type = $type;
        }else{
            $this->ebook_type = "epub";
        }
        if(isset($options["id"])){
            $this->ebook_id = $options["id"];
        }else{
            $this->ebook_id = rand();
        }
        if(isset($options["temp_folder"])){
            $this->temp_folder = $options["temp_folder"];
        }else{
            $this->temp_folder = 'ebooks';
        }

        $this->filepath = $this->temp_folder.'/'.$this->ebook_id.'/';
    }

    // $chapter : array (title, content)
    public function addChapter($chapter){
        array_push($this->chapters,$chapter);
    }
    
    public function setTitle($title){
        $this->ebook_title = $title;
    }

    private function slugify($text, string $divider = '-'){
        // replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, $divider);

        // remove duplicate divider
        $text = preg_replace('~-+~', $divider, $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }


    // GENERATE

    // EPUB EXCLUS
    private function create_epub_meta_inf(){

        $container_content = '<?xml version="1.0" encoding="utf-8"?>

        <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
        <rootfiles>
            <rootfile full-path="OEBPS/book.opf" media-type="application/oebps-package+xml"/>
        </rootfiles>
        </container>';
        try {
            $container_file = fopen($this->filepath."META-INF/container.xml", "w");
            fwrite($container_file, $container_content);
            fclose($container_file);
        }catch(Exception $error) {
            echo '<pre>';
            print_r($error);
            echo '</pre>';
        }

    }

    private function create_epub_opf(){
        $opf_content = '<?xml version="1.0" encoding="utf-8"?>

        <package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="{{UNIQUE_ID}}">

        <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:opf="http://www.idpf.org/2007/opf">

            <dc:title>{{EBOOK_TITLE}}</dc:title>
            <dc:creator opf:role="aut">{{EBOOK_CREATOR}}</dc:creator>
            <dc:language>{{EBOOK_LANG}}</dc:language>
            <dc:identifier id="{{UNIQUE_ID}}">{{UNIQUE_ID}}</dc:identifier>

        </metadata>

        <manifest>
            {{EBOOK_MANIFEST_CHAPTERS}}
            <item id="ncx"       href="book.ncx"        media-type="application/x-dtbncx+xml" />
        </manifest>

        <spine toc="ncx">
            {{EBOOK_SPINE_TOC}}
        </spine>
        </package>';

        if( count($this->chapters) < 1 ){
            echo '<pre>';
                print_r("No chapters");
            echo '</pre>';
        }else{
            $manifest_chapters = '';
            $spine_toc = '';
            foreach( $this->chapters as $key => $chapter ){
                $chapter_num = str_pad( ($key+1) , $this -> chapters_numeration_length, "0", STR_PAD_LEFT);
                $manifest_chapters .='<item id="chapter'.$chapter_num.'" href="chapter'.$chapter_num.'.xhtml" media-type="application/xhtml+xml" />'.chr(10);
                $spine_toc .= '<itemref idref="chapter'.$chapter_num.'" />'.chr(10);
            }

            $tpl_replaces = ["{{UNIQUE_ID}}","{{EBOOK_TITLE}}","{{EBOOK_CREATOR}}","{{EBOOK_LANG}}","{{EBOOK_MANIFEST_CHAPTERS}}","{{EBOOK_SPINE_TOC}}"];
            $ebook_values = ["bookyluke".$this->ebook_id,$this->ebook_title,$this->ebook_creator,$this->ebook_lang,$manifest_chapters,$spine_toc];

            $final_opf_content = str_replace($tpl_replaces, $ebook_values, $opf_content);

            try {
                $opf_file = fopen($this->filepath."OEBPS/book.opf", "w");
                fwrite($opf_file, $final_opf_content);
                fclose($opf_file);
            }catch(Exception $error) {
                echo '<pre>';
                print_r($error);
                echo '</pre>';
            }
        }
        
    }

    private function create_epub_ncx(){
        $ncx_content = '<?xml version="1.0" encoding="utf-8"?>

        <ncx version="2005-1" xml:lang="en" xmlns="http://www.daisy.org/z3986/2005/ncx/">
        <head>
            <meta name="dtb:uid" content="{{UNIQUE_ID}}" />
            <meta name="dtb:depth" content="1" />
            <meta name="dtb:totalPageCount" content="0" />
            <meta name="dtb:maxPageNumber" content="0" />
        </head>

        <docTitle>
            <text>{{EBOOK_TITLE}}</text>
        </docTitle>

        <navMap>
            {{NAV_MAP_CHAPTERS}}
        </navMap>
        </ncx>';

        if( count($this->chapters) < 1 ){
            echo '<pre>';
                print_r("No chapters");
            echo '</pre>';
        }else{
            $nav_map_chapters = '';
            foreach( $this->chapters as $key => $chapter ){
                $chapter_num = str_pad( ($key+1) , $this -> chapters_numeration_length, "0", STR_PAD_LEFT);
                $nav_map_chapters .='<navPoint id="chapter'.$chapter_num.'" playOrder="'.($key + 1).'">
                    <navLabel><text>'.$chapter["title"].'</text></navLabel>
                    <content src="chapter'.$chapter_num.'.xhtml"/>
                    </navPoint>';
            }

            $tpl_replaces = ["{{UNIQUE_ID}}","{{EBOOK_TITLE}}","{{NAV_MAP_CHAPTERS}}"];
            $ebook_values = ["bookyluke".$this->ebook_id,$this->ebook_title,$nav_map_chapters];

            $final_ncx_content = str_replace($tpl_replaces, $ebook_values, $ncx_content);

            try {
                $ncx_file = fopen($this->filepath."OEBPS/book.ncx", "w");
                fwrite($ncx_file, $final_ncx_content);
                fclose($ncx_file);
            }catch(Exception $error) {
                echo '<pre>';
                print_r($error);
                echo '</pre>';
            }
        }
    }

    private function create_epub_chapters(){

        foreach( $this->chapters as $key => $chapter ){
            $chapter_num = str_pad( ($key+1) , $this -> chapters_numeration_length, "0", STR_PAD_LEFT);
            
            try {
                
                // si devuelve array con remplazos, remplazamos imágenes en el XHTML.
                $imgs_replacements = $this->save_epub_chapter_images( $chapter["content"]);
                if ( $imgs_replacements ){
                    // Recuperamos tag de imagen completo.
                    $imgs_tags_in_content = [];
                    preg_match_all('/<img ([^>]*)><\/img>/', $chapter["content"], $imgs_tags_in_content);
                    // Montamos array de TAGS de img envueltos en DIV y P
                    $original_tags = $imgs_tags_in_content[0];
                    $wrapped_tags = [];
                    $img_wrapper_before = '<div style="text-indent:0;text-align:center;margin-right:auto;margin-left:auto;width:99%;page-break-before:auto;page-break-inside:avoid;page-break-after:auto;"><div style="margin-left:0;margin-right:0;text-align:center;text-indent:0;width:100%;"><p style="display:inline-block;text-indent:0;width:100%;">';
                    $img_wrapper_after = '</p></div></div>';
                    foreach ($original_tags as $key => $img_tag) {
                        array_push( $wrapped_tags , $img_wrapper_before.$img_tag.$img_wrapper_after);
                    }
                    // Remplazamos imagenes normales por imágenes ajustadas a tamaño (envueltos en DIV y P)
                    $final_content = str_replace($original_tags, $wrapped_tags, $chapter["content"]);
                    // Remplazamos urls de imagenes
                    $final_content = str_replace($imgs_replacements[0], $imgs_replacements[1], $final_content);
                }else{
                    $final_content = $chapter["content"];
                };

                $current_chapter = '<?xml version="1.0" encoding="utf-8"?>
                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

                <head>
                    <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
                    <title>'.$chapter["title"].'</title>
                </head>

                <body>'.$final_content.'
                </body>
                </html>';

                $chapter_file = fopen($this->filepath."OEBPS/chapter".$chapter_num.".xhtml", "w");
                fwrite($chapter_file, $current_chapter);
                fclose($chapter_file);

                

            }catch(Exception $error) {
                echo '<pre>';
                print_r($error);
                echo '</pre>';
            }
        }
    }

    private function save_epub_chapter_images($html){
        $images = [];
        preg_match_all('/src="([^\'"]*)"/', $html, $images);
        $imgs_replacements = [];
        $imgs_orig_urls = [];
        $imgs_ebook_urls = [];
        // Si hay imágenes, descargamos y añadimos a array de remplazo.
        // Sino, devolvemos FALSE
        if( count($images[1]) > 0 ){
            foreach( $images[1] as $key => $img_url ){
                $pos = strrpos($img_url, "/") + 1;
                $pos_img_params = strrpos($img_url, "?");
                if ($pos === false) { // nota: tres signos de igual
                    break;
                }
                if( $pos_img_params === false ){
                    $file_name = substr($img_url, $pos);
                }else{
                    $file_name = substr($img_url, $pos , ($pos_img_params - $pos));
                }
                // Añadimos un random al principio porque pueden coincidir nombres de archivo.
                $file_name = rand(1, 999999).'_'.$file_name;
                if( $this->grab_image($img_url,$this->filepath."OEBPS/".$file_name) ){
                    array_push($imgs_orig_urls, $img_url);
                    array_push($imgs_ebook_urls, $file_name);
                }
            }
            if( count($imgs_ebook_urls) > 0 ){

                array_push($imgs_replacements , $imgs_orig_urls);
                array_push($imgs_replacements , $imgs_ebook_urls);
                return $imgs_replacements;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
        
    }

    private function create_epub_paths(){
        if( $this->ebook_type == 'epub'){
            $meta_inf_path = $this->filepath.'META-INF/';
            $oebps_path = $this->filepath.'OEBPS/';
            $paths = [$meta_inf_path,$oebps_path];
        }

        foreach ($paths as $key => $path) {
            if (!file_exists($path)) {
                mkdir($path, 0775, true);
            }
        }
        
    }

    private function create_epub_zip(){
        if( $this->ebook_title == ''){
             $this -> filename = $this -> ebook_id;
        }else{
             $this -> filename = $this -> slugify( $this->ebook_title);
        }
        $zipfile = new ZipArchive();
        // file_put_contents($filename, base64_decode("UEsDBAoAAAAAAOmRAT1vYassFAAAABQAAAAIABwAbWltZXR5cGVVVAkAA5adVUyQVx5PdXgLAAEE6AMAAAToAwAAYXBwbGljYXRpb24vZXB1Yit6aXBQSwECHgMKAAAAAADpkQE9b2GrLBQAAAAUAAAACAAYAAAAAAAAAAAApIEAAAAAbWltZXR5cGVVVAUAA5adVUx1eAsAAQToAwAABOgDAABQSwUGAAAAAAEAAQBOAAAAVgAAAAAA"));
        file_put_contents($this->temp_folder.'/'. $this -> filename.'.zip', base64_decode("UEsDBAoAAAAAAOmRAT1vYassFAAAABQAAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi9lcHViK3ppcFBLAQIUAAoAAAAAAOmRAT1vYassFAAAABQAAAAIAAAAAAAAAAAAIAAAAAAAAABtaW1ldHlwZVBLBQYAAAAAAQABADYAAAA6AAAAAAA="));
        $opened_zip = $zipfile->open($this->temp_folder.'/'. $this -> filename.'.zip');
        if ($opened_zip !== TRUE) {
            trigger_error("Could not open archive: " .  $this -> filename.'.zip', E_USER_ERROR);
        }else{
            $metainf_files = scandir($this->filepath.'/META-INF');
            foreach ($metainf_files as $key => $metainf_filename) {
                if ('.' !== $metainf_filename && '..' !== $metainf_filename){
                    $zipfile->addFile($this->filepath.'/META-INF/'.$metainf_filename, 'META-INF/'.$metainf_filename);
                }
            }
            $oebps_files = scandir($this->filepath.'/OEBPS');
            foreach ($oebps_files as $key => $oebps_filename) {
                if ('.' !== $oebps_filename && '..' !== $oebps_filename){
                    $zipfile->addFile($this->filepath.'/OEBPS/'.$oebps_filename, 'OEBPS/'.$oebps_filename);
                }
            }
            $zipfile->close();
        }

        rename($this->temp_folder.'/'. $this -> filename.'.zip', $this->temp_folder.'/'. $this -> filename.'.epub');

        return $this->temp_folder.'/'. $this -> filename.'.epub';
        
    }

    private function remove_temp_folder(){

        $dir = $this->filepath;
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
                    RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        
    }

    // COMMON
    public function grab_image($url,$saveto){
        try {
            $ch = curl_init ($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
            $raw=curl_exec($ch);
            curl_close ($ch);
            if(file_exists($saveto)){
                unlink($saveto);
            }
            $fp = fopen($saveto,'x');
            fwrite($fp, $raw);
            fclose($fp);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
        
    }

    public function create_file(){

            
        if( $this->ebook_type == "epub"){
            $this->create_epub_paths();
            $this->create_epub_meta_inf();
            $this->create_epub_opf();
            $this->create_epub_ncx();
            $this->create_epub_chapters();
            $generated_file = $this->create_epub_zip();
            $this->remove_temp_folder();
            return $generated_file;
        }
        
    }

    public function download_file(){
        $file_location = $this->create_file();
        header('Content-type:  application/epub+zip');
        header('Content-Length: ' . filesize($file_location));
        header('Content-Disposition: attachment; filename="' . $this -> filename . '.' .$this->ebook_type . '"');
        readfile($file_location);
        ignore_user_abort(true);
        if (connection_aborted()) {
            unlink($file_location);
        }
        unlink($file_location);
    }
}