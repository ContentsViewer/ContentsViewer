<?php


class OutlineText{
    
    private static $indentSpace = 4;
    private static $htmlTagList = [
        "script", "noscript",

        "p", "pre", "ol", "ul", "li", "dl", "dt", "dd", "figure", "figcaption", "div",

        "a", "em", "strong", "small", "s", "cite", "q", "i", "b", "span",

        "h1", "h2", "h3", "h4", "h5", "h6",

        "table", "caption", "tbody", "thead", "tr", "td", "th",

        "form", "button", "textarea"

    ];


    //function __construct(){
        
    //}







    private static $blockSeparators;
    private static $patternTagBlockStartTag;
    private static $patternTagBlockEndTag;


    public static function Init(){
    
        // ブロックごとに区切るためのpattern
        static::$blockSeparators = "/";
        $blockTagCount = count(static::$htmlTagList);
        for($i = 0; $i < $blockTagCount; $i++){
            static::$blockSeparators .= "(<" . static::$htmlTagList[$i] . "\b.*?>)|(<\/" . static::$htmlTagList[$i] . " *?>)";
            if($i < $blockTagCount - 1){
                static::$blockSeparators .= "|";
            }
        }

        static::$blockSeparators .= "|(`)";

        static::$blockSeparators .= "/i";




        static::$patternTagBlockStartTag = "/";
        $blockTagCount = count(static::$htmlTagList);
        for($i = 0; $i < $blockTagCount; $i++){
            static::$patternTagBlockStartTag .=  "(<" . static::$htmlTagList[$i] . "\b.*?>)";
            if($i < $blockTagCount - 1){
                static::$patternTagBlockStartTag .= "|";
            }
        }

        static::$patternTagBlockStartTag .= "/i";




        
        static::$patternTagBlockEndTag = "/";
        $blockTagCount = count(static::$htmlTagList);
        for($i = 0; $i < $blockTagCount; $i++){
            static::$patternTagBlockEndTag .= "(<\/" . static::$htmlTagList[$i] . " *?>)";
            if($i < $blockTagCount - 1){
                static::$patternTagBlockEndTag .= "|";
            }
        }

        static::$patternTagBlockEndTag .= "/i";



    }


    public static function Decode($plainText){
        $output = "";

        $chunkList = static::SplitToChunk($plainText);

        // $output .= "<pre>";
        // $output .= var_dump($chunkList);
        // $output .= "</pre>";

        // --- 複数のチャンクをまたいで存在する情報 ----

        $indentLevelPrevious = -1;
        $indentLevel = 0;

        $beginParagraph = false;
        $beginList = false;
        $beginTable = false;
        $listItemIndentLevel = 0;
        $tableColumnHeadCount = 0;
        $beginTableBody = false;
        // $beginCodeBlock = false;
        // $codeBlockIndentLevel = 0;
        $beginSectionTitle = false;
        $beginTableRow = false;

        $skipNextLineChunk = false;

        // End 複数のチャンクをまたいで存在する情報 ----

        // --- 各チャンクごとに対して -----------------------------------
        $chunkCount = count($chunkList);
        for($i = 0; $i < $chunkCount; $i++){
            $chunk = $chunkList[$i];

            // if($chunk["isInlineCode"]){
            //     echo var_dump($chunk);
            // }


            // if($beginCodeBlock){
            //     if($chunk["indentLevel"] == $codeBlockIndentLevel 
            //         && preg_match("/^```*/", $chunk["content"],$matches) === 1){
                    
            //         $output .= "</pre>";
            //         $beginCodeBlock = false;


            //     }
            //     else{
            //         //echo $codeBlockIndentLevel;

            //         if($chunk["indentLevel"] == -1){
            //             if($chunk["content"] == ""){
            //                 $output .= "\n";
            //             }
            //         }
            //         else{
            //             for($j = 0; $j < $chunk["spaceCount"]; $j++){
            //                 $output .= " ";
            //             }
            //         }

            //         $output .= static::EscapeSpecialCharacters($chunk["content"]) . "\n";


            //     }

            //     continue;

            // }



            // インデントレベル値が設定されていない.
            // これの可能性は,
            //  * plaintextで文中のとき
            //  * 空白行のとき
            if($chunk["indentLevel"] == -1){
                

                    
                if($chunk["isInlineCode"]){
                    $output .= "<code>" . static::EscapeSpecialCharacters($chunk["content"]) . "</code>";
                    // echo $chunk["content"];
                }
                else if($chunk["isTagElement"]){

                    $output .= $chunk["content"];
                }
                else{
                    $output .= static::DecodeSpanElements($chunk["content"]);
                }





                // 空白行のとき
                if($chunk["content"] == ""){
                            
                    if($beginSectionTitle){
                        $output .= "</h" . ($indentLevel + 2) . ">";
                        $beginSectionTitle = false;
                    }


                    if($beginTableRow){
                        $output .= "</tr>";
                        $beginTableRow = false;
                    }



                    if($beginParagraph){
                        $output .= "</p>";
                        $beginParagraph = false;
                    }

                    if($beginList){
                        //echo $listItemIndentLevel;
                        $indentLevelPrevious -= $listItemIndentLevel;

                        while($listItemIndentLevel >= 0){
                            $output .= "</li></ul>";
                            //echo "sa";
                            $listItemIndentLevel--;
                        }

                        $listItemIndentLevel = 0;

                        //Debug::Log($output);
                        $beginList = false;
                    }

                    if($beginTable){
                        $output .= "</table>";
                        $tableColumnHeadCount = 0;
                        $beginTableBody = false;
                        $beginTable = false;
                    }
                }


                continue;
            }

            if($skipNextLineChunk){
                //echo "sasasad";
                

                $skipNextLineChunk = false;

                continue;
            }
            
            if($beginSectionTitle){
                $output .= "</h" . ($indentLevel + 2) . ">";
                $beginSectionTitle = false;
            }


            if($beginTableRow){
                $output .= "</tr>";
                $beginTableRow = false;
            }



            //
            // インデントレベルの変化を見る
            //
            // 処理過程
            //  リストブロック中である
            //   リストアイテムのレベルを変更する
            //
            //  その他
            //   セクションのレベルを変更する
            // 
            // 行頭がSpaceのみの場合でも, 
            // インデントレベル変更によるセクションタグの作成が起こる可能性がある.
            $indentLevel = $chunk["indentLevel"];

            // 右へインデント
            if($indentLevel > $indentLevelPrevious){
                $levelDiff = $indentLevel - $indentLevelPrevious;

                while($levelDiff > 0){
                    //echo "->:" . $blocks[$j];

                    if($beginList){
                        $output .= "<ul>";
                        $listItemIndentLevel++;
                    }else{
                        $output .= "<div class='Section'>";
                    }


                    $levelDiff--;
                }
                
            }

            // 左へインデント
            if($indentLevel < $indentLevelPrevious){
                $levelDiff = $indentLevelPrevious - $indentLevel;
                if($beginList){
                    $output .= "</li>";
                }

                while($levelDiff > 0){
                    //echo "<-:" . $blocks[$j];


                    if($beginList){
                        $output .= "</ul></li>";
                        $listItemIndentLevel--;
                    }else{
                        $output .= "</div>";
                    }



                    $levelDiff--;
                }
            }
            
            // インデントそのまま
            if($indentLevel == $indentLevelPrevious){
                if($beginList){
                    $output .= "</li>";
                }
            }

            $indentLevelPrevious = $indentLevel;

            // End インデントの変化を見る ---



            // タグ要素
            if($chunk["isTagElement"]){
                $output .= $chunk["content"];

                continue;
            }

            
            // --- Code block ----------------------
            if($chunk["isCodeBlock"]){
                
                // code blockに入る
                //var_dump($matches);
                $output .= "<pre class='brush: ". $chunk["codeBlockAttribute"] . ";'>";

                $output .= static::EscapeSpecialCharacters($chunk["content"]);

                $output .= "</pre>";

                continue;
            
            
            } // End Code block ------------------


            // 空文字の時
            if($chunk["content"] == ""){
                continue;
            }
            

            $ret = 0;
            $matches = array();
            $header = "";
            $nextLine = "";
            if($chunk["nextLineChunkIndex"] != -1 && $chunkList[$chunk["nextLineChunkIndex"]]["indentLevel"] == $chunk["indentLevel"]){
                $nextLine = $chunkList[$chunk["nextLineChunkIndex"]]["content"];
            }
            
            // --- 見出し --------------------
            if($ret = static::CheckHeaderLine($chunk["content"], $nextLine, $header)){
                
                $output .= "<h" . ($indentLevel + 2) . " ";
                if($indentLevel <= 0){
                    $output .= "class = 'SectionTitle'>";
                }
                elseif($indentLevel == 1){
                    $output .= "class = 'SubSectionTitle'>";
                }
                elseif($indentLevel == 2){
                    $output .= "class = 'SubSubSectionTitle'>";
                }
                else{
                    $output .= "class = 'SubSubSubSectionTitle'>";
                }

                $output .= static::DecodeSpanElements($header);

                if($ret == 2){
                    $skipNextLineChunk = true;
                }

                $beginSectionTitle = true;
            } // End 見出し -----------
            
            // --- List ----------------------------                     
            elseif(preg_match("/^\*/", $chunk["content"]) === 1){



                if(!$beginList){
                    $output .= "<ul>";
                    $listItemIndentLevel = 0;
                    $beginList = true;
                }

                $output .= "<li>";


                $output .= static::DecodeSpanElements(substr($chunk["content"], 1));
                
                $beginListItem = true;
            } // End List --------------------

            // --- Tree ------------------------------                    
            elseif(preg_match("/^\+/", $chunk["content"]) === 1){

                if(!$beginList){
                    $output .= "<ul class='Tree'>";
                    $listItemIndentLevel = 0;
                    $beginList = true;
                }

                
                $output .= "<li>";


                $output .= static::DecodeSpanElements(substr($chunk["content"], 1));
                
                $beginListItem = true;
            } // End Tree -----------------------

            // --- Figure Image -------------------------
            elseif(preg_match("/^!\[(.*)?\]\((.*)?\)/", $chunk["content"],$matches)){
                //$temp = substr($blocks[$j], 0, $matches[0][1]);
                $temp = "<figure><img src='" . $matches[2] . "' alt='". 
                    $matches[1] ."'/><figcaption>" . 
                    $matches[1] . "</figcaption></figure>";

                //$temp .= substr($blocks[$j], $matches[0][1] + strlen($matches[0][0]));
                
                $output .= $temp;
            }
            // End Figure Image -------------------

            // --- Table ----------------------------------
            elseif(($ret = static::CheckTableLine($chunk["content"], $matches)) != -1){
                

                $temp = "";

                if(!$beginTable){
                    $output .= "<table>";

                    if($ret == -3){
                        $output .= "<caption>" . static::DecodeSpanElements($matches[0]) . "</caption>";
                    }

                    $output .= "<thead>";
                    $beginTable = true;
                }

                if($ret == -2){
                    $output .= "</thead>";
                    $output .= "<tbody>";

                    $beginTableBody = true;
                }


                if($ret >= 0){
                    $beginTableRow = true;
                    $tableColumnHeadCount = $ret;

                    $output .= "<tr>";

                    //echo $tableColumnHeadCount;
                    //var_dump($matches);
                    $tableElementCount = count($matches);
                    for($k = 0; $k < $tableElementCount; $k++){
                        if( ($beginTableBody && $k < $tableColumnHeadCount) || !$beginTableBody){
                            $temp .= "<th>";
                        }
                        else {
                            $temp .= "<td>";
                        }


                        $temp .= static::DecodeSpanElements($matches[$k]);

                        if( ($beginTableBody && $k < $tableColumnHeadCount)  || !$beginTableBody){
                            $temp .= "</th>";
                        }
                        else {
                            $temp .= "</td>";
                        }
                    }

                }

                $output .= $temp;
            } // End Table ----------

            else {
                if(!$beginParagraph){
                    

                    $output .= "<p>";

                    $output .= static::DecodeSpanElements($chunk["content"]);
                    $beginParagraph = true;


                }
                else{
                    $output .= static::DecodeSpanElements($chunk["content"]);
                }
            }


    
        } // End 各チャンクごとに対して ----


        // すべてのチャンクの処理を終えた場合

        if($beginParagraph){
            $output .= "</p>";
        }

        if($beginList){
            while($listItemIndentLevel >= 0){
                $output .= "</li></ul>";
                $listItemIndentLevel--;
            }
            $listItemIndentLevel = 0;
        }

        if($beginTable){
            if($beginTableBody){
                $output .= "</tbody>";
            }
            else{
                $output .= "</thead>";
            }

            $output .= "</table>";
        }

        //echo ": " . $indentLevelPrev;
        while($indentLevelPrevious >= 0){
            $output .= "</div>";
            $indentLevelPrevious--;
        }
        //Debug::Log($output);



        return $output;

    }



    

    private static function DecodeSpanElements($text){
        
        $matches = array();

        if(preg_match_all("/\[(.*)?\]\((.*)?\)/", $text,$matches, PREG_OFFSET_CAPTURE)){
            // $temp = substr($text, 0, $matches[0][1]);
            // $temp .= "<a href='" . $matches[2][0] . "'>" . $matches[1][0] . "</a>";
            // $temp .= substr($text, $matches[0][1] + strlen($matches[0][0]));
            
            // return $temp;


            $output = "";
            $matchedCount = count($matches[0]);
            $offset = 0;

            for($i = 0; $i < $matchedCount; $i++){
                

                $output .= substr($text, $offset, $matches[0][$i][1] - $offset);
                $output .= "<a href='" . $matches[2][$i][0] . "'>" . $matches[1][$i][0] . "</a>";
                $offset = $matches[0][$i][1] +  strlen($matches[0][$i][0]);
                
            }
            $output .= substr($text, $offset);
           

            return $output;
        }
        // elseif(preg_match_all("/`([^`]+)`/", $text,$matches, PREG_OFFSET_CAPTURE)){
        //     //var_dump($matches);
        //     $output = "";
        //     $matchedCount = count($matches[0]);
        //     $offset = 0;

        //     for($i = 0; $i < $matchedCount; $i++){
        //         $output .= substr($text, $offset, $matches[0][$i][1] - $offset);
        //         $output .= "<code>" . $matches[1][$i][0] . "</code>";
        //         $offset = $matches[0][$i][1] +  strlen($matches[0][$i][0]);
                
        //     }
        //     $output .= substr($text, $offset);
           

        //     return $output;
        // }
        else{
            return $text;
        }

        

        return "";
    }

    private static function EscapeSpecialCharacters($text){
        $text = str_replace("&", "&amp;", $text);
        $text = str_replace("<", "&lt;", $text);
        $text = str_replace(">", "&gt;", $text);

        return $text;

    }

    private static function CreateChunk(){
        return ["indentLevel" => -1, "spaceCount" => 0, "isTagElement" => false, "isCodeBlock" => false,
         "content" => "", "nextLineChunkIndex"=>-1, "codeBlockAttribute" => "", "isInlineCode" => false];
    }
    private static function SplitToChunk($plainText){
        
        // chunkの追加のタイミング
        //  * tagBlockから抜けたとき
        //  * tagBlockに入ったとき
        //  * tagBlock内ではなく and 行が変わったとき 
        
        $chunkList = array();
        $chunk = static::CreateChunk();




        $lines = explode("\n", $plainText);
        $lineCount = count($lines);

        
        // --- 複数行にまたがって存在する情報 -------------
        $startSpaceCount = 0;
        $tagBlockLevel = 0;
        $tagBlockLevelPrevious = 0;

        $isStartWriting = false;

        $chunkIndex = 0;
        $lineStartChunkIndex = 0;

        $isInInlineCode = false;
        $isInCodeBlock = false;
        $codeBlockIndentLevel = 0;

        // End 複数行にまたがって存在する情報 ----

        for($i = 0; $i < $lineCount; $i++){
            
            // 書き込みの始まりを確認
            // 最初の空白文字の計算
            if(!$isStartWriting){
                $wordCount = strlen($lines[$i]);
                
                for($startSpaceCount = 0; $startSpaceCount < $wordCount; $startSpaceCount++){
                    if($lines[$i][$startSpaceCount] != ' '){
                        break;
                    }
                }

                if($startSpaceCount != $wordCount){
                    $isStartWriting = true;
                    //echo $lines[$i] . "<br>";
                }
                
            }

            // まだ文章が始まっていないとき
            if(!$isStartWriting){
                continue;
            }


            
            // --- indentLevelの計算 -----------------------------
            $wordCount = strlen($lines[$i]);
            $spaceCount = 0;
            for($spaceCount = 0; $spaceCount < $wordCount; $spaceCount++){
                if($lines[$i][$spaceCount] != ' '){
                    break;
                }
            }

            $isEmpty = false;
            // すべて, Spaceのとき
            if($spaceCount == $wordCount){
                $isEmpty = true;
                //echo "em";
            }

            $indentLevel = ($spaceCount - $startSpaceCount) / static::$indentSpace;
            //echo $startSpaceCount;

            // End indentLevelの計算 ------------------------

            if($isInCodeBlock){

                // コードブロックから出る
                if($codeBlockIndentLevel == $indentLevel && preg_match("/^ *```(.*)/", $lines[$i], $matches) === 1){
                    $isInCodeBlock = false;


                    $chunkIndex++;
                    $chunkList[] = $chunk;

                    $chunk = static::CreateChunk();

                    



                    continue;
                }

                // コードブロック内の処理
                else{

                    $chunk["content"] .= $lines[$i] . "\n";
                    

                    continue;
                }

            }
            else{

                // コードブロック内に入る
                if($tagBlockLevel <= 0 && preg_match("/ *```(.*)/", $lines[$i], $matches) === 1){
                    $isInCodeBlock = true;
                    $codeBlockIndentLevel = $indentLevel;
                
                    $chunk["indentLevel"] = $indentLevel;
                    $chunk["spaceCount"] = $spaceCount;
                    $chunk["isCodeBlock"] = true;
                    $chunk["codeBlockAttribute"] = $matches[1];

                    
                    continue;
                }
            }
            
            
            // 空白行のとき
            if($isEmpty){
                if($tagBlockLevel > 0){
                    $chunk["content"] .= "\n";
                }
                else{
                    

                    for($j = $lineStartChunkIndex; $j < $chunkIndex; $j++){
                        $chunkList[$j]["nextLineChunkIndex"] = $chunkIndex;
                    }
                    $lineStartChunkIndex = $chunkIndex;
                    
                    $chunkIndex++;


                    $chunkList[] = $chunk;
                    $chunk = static::CreateChunk();


                }

                continue;
            }

            if($tagBlockLevel <= 0){
                $chunk["indentLevel"] = $indentLevel;
                $chunk["spaceCount"] = $spaceCount;
            }

            
            $blocks = preg_split(static::$blockSeparators, $lines[$i],-1,PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            //var_dump($blocks);
            $blockCount = count($blocks);


            $beginInlineCode = false;
            
            // --- ブロックごとの処理 ----
            for($j = 0; $j < $blockCount; $j++){

                //echo $blocks[$j] . "\n";

                if($beginInlineCode){

                    // インラインコードから抜ける
                    if($blocks[$j] == "`"){
                        $beginInlineCode = false;


                        $chunkIndex++;
                        $chunkList[] = $chunk;
                        //var_dump($chunk);
                        
                        $chunk = static::CreateChunk();




                        continue;
                    }

                    // インラインコード内
                    else{
                        $chunk["content"] .= $blocks[$j];
                        //echo $blocks[$j];
                        continue;
                    }

                }
                else{

                    // インラインコードに入る
                    if($tagBlockLevel <= 0 && $blocks[$j] == "`"){
                        $chunkIndex++;

                        $chunkList[] = $chunk;
                        $chunk = static::CreateChunk();


                        $beginInlineCode = true;

                        $chunk["isInlineCode"] = true;

                        //echo "34y";

                        continue;
                    }
                }
                
                
                $isTag = false;


                if(preg_match(static::$patternTagBlockStartTag, $blocks[$j]) === 1){
                
                    $tagBlockLevel++;
                    $isTag = true;
                }

                if(preg_match(static::$patternTagBlockEndTag, $blocks[$j]) === 1){
                
                    $tagBlockLevel--;
                    $isTag = true;
                }


                if($tagBlockLevel != $tagBlockLevelPrevious){

                    // タグブロック内に入った
                    if($tagBlockLevel > 0 && $tagBlockLevelPrevious <= 0){
                        $chunkIndex++;

                        $chunkList[] = $chunk;
                        $chunk = static::CreateChunk();
                        $chunk["isTagElement"] = true;
                        
                        $chunk["content"] .= $blocks[$j];
                    }

                    //　タグブロックから出た
                    elseif($tagBlockLevel <= 0 && $tagBlockLevelPrevious > 0){
                        $chunkIndex++;

                        $chunk["content"] .= $blocks[$j];

                        $chunkList[] = $chunk;
                        $chunk = static::CreateChunk();
                        $chunk["isTagElement"] = false;

                    }

                    // タグブロック内での変化
                    else{
                        
                        $chunk["content"] .= $blocks[$j];
                    }

                    $tagBlockLevelPrevious = $tagBlockLevel;
                }
                
                // タグブロックの変化がない 
                else{
                    if($tagBlockLevel <= 0){
                        if($j == 0){
                            $blocks[$j] = substr($blocks[$j], $spaceCount);
                        }
                        $chunk["content"] .= $blocks[$j];
                    }
                    else{
    
                        $chunk["content"] .= $blocks[$j];
                    }
                }


                

            } // End ブロックのごとの処理 ---

            // 行の終わり & タグブロック内ではないとき
            if($tagBlockLevel <= 0){
                

                for($j = $lineStartChunkIndex; $j < $chunkIndex; $j++){
                    $chunkList[$j]["nextLineChunkIndex"] = $chunkIndex;
                }
                $lineStartChunkIndex = $chunkIndex;


                $chunkIndex++;
                
                $chunkList[] = $chunk;
                $chunk = static::CreateChunk();

            }

            // 行の終わり & タグブロック内のとき
            else{
                $chunk["content"] .= "\n";
            }
        } // End 各行ごとの処理 ---


        $chunkList[] = $chunk;

        return $chunkList;
    }

    private static function CheckHeaderLine($line, $nextLine, &$header){
        $headHasHash = false;
        $nextLineIsHorizontalLine = false;

        if(preg_match("/^\#/", $line) === 1){
            $headHasHash = true;
            

        }
        
        if(preg_match("/^---*$/", $nextLine) === 1
                || preg_match("/^===*$/", $nextLine) === 1
                || preg_match("/^___*$/", $nextLine) === 1){
            $nextLineIsHorizontalLine = true;
            
        }

        if($headHasHash || $nextLineIsHorizontalLine){
            if($headHasHash){
                $header = substr($line, 1);
            }
            else{
                $header = $line;
            }

            if($nextLineIsHorizontalLine){
                return 2;
            }

            return 1;
        }



        return 0;
    }

    //
    // @return:
    //  -1: Not table
    //  -2: It's a mark of the begining of the tableBody
    //  -3: It's a table caption.
    //  over zero: column head count
    private static function CheckTableLine($line, &$matches){
        $matches = [];


        // 空文字のとき
        if($line == ""){
            return -1;
        }


        $temp = [];
        
        $blocks = explode("|", $line);

        $columnHeadCount = 0;

        //echo $line;
        
        $blockCount = count($blocks);

        
        // 行頭が|で始まっているか
        if($blockCount > 0 && $blocks[0] != ""){
            // 先頭が空文字でない. 行頭が|で始まっていない.
            return -1;
        }

        // captionの認識
        if($blockCount == 2 && preg_match("/\[(.*)?\]/", $blocks[1], $temp)){
            $matches[] = $temp[1];
            return -3;
        }

        for($i = 0; $i < $blockCount; $i++){

            

            // body開始トークンの認識
            if(preg_match("/^-{3,}$/", $blocks[$i])){
                // |と|の間に-が三個以上ある場合
                //echo "dadada";
                return -2;
            }

            


            // 行末が|で終わっているか
            if($i == $blockCount - 1 && $blocks[$i] != ""){

                // 行末が|で終わっていない
                return -1;
            }

            
            // 列ヘッド終了の認識
            if(0 < $i && $i < $blockCount - 1 && $blocks[$i] == ""){
               
                // ||が連続である場合
                $columnHeadCount = $i - 1;

                //echo $columnHeadCount;
            }


            // 
            if($blocks[$i] != ""){
                //echo $blocks[$i];
                $matches[] = $blocks[$i];
            }

        }
        //echo var_dump($blocks);
        return $columnHeadCount;

    }

    

} // End class OutlineText



?>