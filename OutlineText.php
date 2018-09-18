<?php




/*
*
*/






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

    private static $specialCharacterEscapeExclusionPattern = 
        "/(<br *?>)|(<br *\/?>)|(<img.*?>)|(<img.*?\/>)|(<hr *?>)|(<hr *\/?>)|(<input.*?>)|(<input.*?\/>)/";
    

    private static $commentStartToken = "<!--";
    private static $commentEndToken = "-->";


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




    //
    // 処理の流れは以下の通り.
    //  1. Chunkに分ける
    //  2. Chunkごとにデコード処理を行う.
    //   2.1. 処理ごとにspanElementの処理を行う.
    //
    public static function Decode($plainText){
        $output = "";

        $chunkList = static::SplitToChunk($plainText);

        //  $output .= "<pre>";
        //  $output .= var_dump($chunkList);
        //  $output .= "</pre>";

        // --- 複数のチャンクをまたいで存在する情報 ----

        $indentLevelPrevious = -1;
        $indentLevel = 0;

        $beginParagraph = false;
        $beginList = false;
        $isOrderdList = false;
        $beginTable = false;
        $listItemIndentLevel = 0;
        $tableColumnHeadCount = 0;
        $beginTableBody = false;
        $beginSectionTitle = false;
        $beginTableRow = false;

        $skipNextLineChunk = false;

        // End 複数のチャンクをまたいで存在する情報 ----

        // --- 各チャンクごとに対して -----------------------------------
        $chunkCount = count($chunkList);
        for($i = 0; $i < $chunkCount; $i++){
            $chunk = $chunkList[$i];

            $nextChunk = null;
            if($i < $chunkCount - 1){
                $nextChunk = $chunkList[$i + 1];
            }


            // インデントレベル値が設定されていない.
            // これの可能性は,
            //  * plaintextで文中のとき
            //  * 空白行のとき
            if($chunk["indentLevel"] == -1){
                

                    
                if($chunk["isInlineCode"]){
                    $output .= "<code>" . static::EscapeSpecialCharactersForce($chunk["content"]) . "</code>";
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
                            $output .= "</li>" . ($isOrderdList ? "</ol>" : "</ul>");
                            
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
                        $output .= ($isOrderdList ? "<ol>" : "<ul>");
                        $listItemIndentLevel++;
                    }else{
                        $output .= "<div class='section'>";
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
                        $output .= ($isOrderdList ? "</ol>" : "</ul>") . "</li>";
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

                $output .= static::EscapeSpecialCharactersForce($chunk["content"]);

                $output .= "</pre>";

                continue;
            
            
            } // End Code block ------------------


            // 空文字の時
            // インデント値はあるが, 空文字
            // その次がインラインコード, html要素のときに起こる.
            if($chunk["content"] == ""){

                // 次がインラインコードのときは, このまま処理を続ける.
                // 次がインラインコードのとき, このまま処理を続けないと,
                // <p></p>で囲まれない.
                //
                // 逆にその次が, html要素のときは, <p></p>で囲まれてしまい, 
                // <p></p>で囲めない要素が来た時によろしくない.
                if(!is_null($nextChunk) && $nextChunk["isInlineCode"]){
                    
                }
                else{
                    // 次がhtml要素などはこのまま処理を続けない.
                    continue;
                }
                //continue;
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
                    $output .= "class = 'section-title'>";
                }
                elseif($indentLevel == 1){
                    $output .= "class = 'sub-section-title'>";
                }
                elseif($indentLevel == 2){
                    $output .= "class = 'sub-sub-section-title'>";
                }
                else{
                    $output .= "class = 'sub-sub-sub-section-title'>";
                }

                $output .= static::DecodeSpanElements($header);

                if($ret == 2){
                    $skipNextLineChunk = true;
                }

                $beginSectionTitle = true;
            } // End 見出し -----------
            
            // --- List ----------------------------                     
            elseif(preg_match("/^\* /", $chunk["content"]) === 1){



                if(!$beginList){
                    $output .= "<ul>";
                    $listItemIndentLevel = 0;
                    $beginList = true;
                    $isOrderdList = false;
                }

                $output .= "<li>";


                $output .= static::DecodeSpanElements(substr($chunk["content"], 1));
                
                $beginListItem = true;
            } // End List --------------------

            // --- Tree ------------------------------                    
            elseif(preg_match("/^\+ /", $chunk["content"]) === 1){

                if(!$beginList){
                    $output .= "<ul class='tree'>";
                    $listItemIndentLevel = 0;
                    $beginList = true;
                    $isOrderdList = false;
                }

                
                $output .= "<li>";


                $output .= static::DecodeSpanElements(substr($chunk["content"], 1));
                
                $beginListItem = true;
            } // End Tree -----------------------

            // --- 番号付きList ----------------------
            elseif(preg_match("/^([0-9]+.)* /", $chunk["content"]) === 1){
                if(!$beginList){
                    $output .= "<ol>";
                    $listItemIndentLevel = 0;
                    $beginList = true;
                    $isOrderdList = true;
                }

                
                $output .= "<li>";


                $output .= static::DecodeSpanElements(substr($chunk["content"], strpos($chunk["content"], " ")));
                
                $beginListItem = true;
            
            } // End 番号付きList -----------

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


    private static function CheckHeaderLine($line, $nextLine, &$header){
        $headHasHash = false;
        $nextLineIsHorizontalLine = false;

        if(preg_match("/^\# /", $line) === 1){
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





















    private static $spanElementPatternTable = [
        ["/\[(.*?)\]\((.*?)\)/", "<a href='{1}'>{0}</a>"],
        ["/\*\*(.*?)\*\*/", "<strong>{0}</strong>"],
        ["/\/\/(.*?)\/\//", "<em>{0}</em>"],
        ["/__(.*?)__/", "<mark>{0}</mark>"],
        ["/~~(.*?)~~/", "<s>{0}</s>"]
    ];

    private static function DecodeSpanElements($text){

        $spanElementPatternTableCount = count(static::$spanElementPatternTable);

        // --- マッチ情報の初期化 ------
        $patternMatchInfos = array();


        for($i = 0; $i < $spanElementPatternTableCount; $i++){
            
            $patternMatchInfos[] = ["matches" => array(), "iteratorIndex" => 0, "matchedCount" => 0];

        }


        // end マッチ情報の初期化 ----------


        // パターンのマッチ
        for($i = 0; $i < $spanElementPatternTableCount; $i++){
            preg_match_all(static::$spanElementPatternTable[$i][0], $text, $patternMatchInfos[$i]["matches"], PREG_OFFSET_CAPTURE);
            $patternMatchInfos[$i]["matchedCount"] = count($patternMatchInfos[$i]["matches"][0]);
        }

        //var_dump($patternMatchInfos);

        $currentPosition = 0;
        $output = "";


        for(;;){

            // マッチしたパターンのうちパターン始まり位置が若いのを選ぶ
            $focusedPatternIndex = -1;
            for($i = 0; $i < $spanElementPatternTableCount; $i++){


                if($patternMatchInfos[$i]["matchedCount"] <= 0 ||
                   $patternMatchInfos[$i]["iteratorIndex"] >= $patternMatchInfos[$i]["matchedCount"]){
                    continue;
                }

                if($focusedPatternIndex < 0){
                    $focusedPatternIndex = $i;
                    continue;
                }

                
                if($patternMatchInfos[$i]["matches"][0][ $patternMatchInfos[$i]["iteratorIndex"] ][1] < 
                   $patternMatchInfos[$focusedPatternIndex]["matches"][0][ $patternMatchInfos[$focusedPatternIndex]["iteratorIndex"] ][1]){
                       $focusedPatternIndex = $i;
                }
            }

            if($focusedPatternIndex < 0){
                break;
            }



            $focusedPatternIteratorIndex =  $patternMatchInfos[$focusedPatternIndex]["iteratorIndex"];
            $focusedPatternStartPosition = $patternMatchInfos[$focusedPatternIndex]["matches"][0][$focusedPatternIteratorIndex ][1];

            // パターン開始位置が現在の位置よりも前のとき
            // 直前のパターン内にパターン文字が含まれている可能性が高いので, currentPositionから再びパターンを検索する.
            //  例:
            //   [abc](**abc) **strong**
            //
            //   これは, 何も対策しないと次のようになる.
            //   <a href='**abc'>abc</a> **strong**
            //
            if($focusedPatternStartPosition < $currentPosition){
                preg_match_all(static::$spanElementPatternTable[$focusedPatternIndex][0], $text, $patternMatchInfos[$focusedPatternIndex]["matches"], PREG_OFFSET_CAPTURE, $currentPosition);
                $patternMatchInfos[$focusedPatternIndex]["matchedCount"] = count($patternMatchInfos[$focusedPatternIndex]["matches"][0]);
                $patternMatchInfos[$focusedPatternIndex]["iteratorIndex"] = 0;
                continue;
            }

            $focusedPatternString = $patternMatchInfos[$focusedPatternIndex]["matches"][0][ $focusedPatternIteratorIndex ][0];

            //echo $focusedPatternStartPosition;

            // パターン前の文字列を出力
            $output .= static::EscapeSpecialCharacters(substr($text, $currentPosition, $focusedPatternStartPosition - $currentPosition));


            $spanString = static::$spanElementPatternTable[$focusedPatternIndex][1];
            $capturedCount = count($patternMatchInfos[$focusedPatternIndex]["matches"]) - 1;
            for($i = 0; $i < $capturedCount; $i++){
                $spanString = str_replace("{" . ($i) . "}", $patternMatchInfos[$focusedPatternIndex]["matches"][$i+1][$focusedPatternIteratorIndex][0], $spanString);

            }

            $output .= $spanString;

            $currentPosition = $focusedPatternStartPosition + strlen($focusedPatternString);

            $patternMatchInfos[$focusedPatternIndex]["iteratorIndex"]++;
            //break;
        }


        
        $output .= static::EscapeSpecialCharacters(substr($text, $currentPosition));

        return $output;

    }

    private static function EscapeSpecialCharacters($text){

        $blocks = preg_split(static::$specialCharacterEscapeExclusionPattern, $text,-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        //var_dump($blocks);

        $blocksCount = count($blocks);
        
        for($i = 0; $i < $blocksCount; $i++){
            if(!preg_match(static::$specialCharacterEscapeExclusionPattern, $blocks[$i])){
                        
                $blocks[$i] = static::EscapeSpecialCharactersForce($blocks[$i]);
            }
        }

        


        return implode($blocks);

    }






    private static function EscapeSpecialCharactersForce($text){
           
        $text = str_replace("&", "&amp;", $text);
        $text = str_replace("<", "&lt;", $text);
        $text = str_replace(">", "&gt;", $text);

        return $text;

    }












    // 
    // chunkについて:
    //  デコード処理単位である.
    //  まず, OutlineTextのデコード(タグのエスケープは除く)対象とそうでないものにチャンク分けは行われる.
    //  デコード対象とならないものは, インラインコードの中, コードブロックの中, タグブロックの中である.
    //  また, 行替えごとにチャンクは分けられる.
    //
    // chunkの追加のタイミング
    //  
    //  * tagBlockから抜けたとき
    //  * tagBlockに入ったとき
    //  * tagBlock内ではなく and 空白行のとき
    //  * CodeBlockから出るとき
    //  * インラインコードに入る or 出るとき
    //  * tagBlock内ではなく and 行が終わるとき
    //
    //  以下がその例. `(<)`でチャンクの追加が行われる
    //  
    /*
            Hello!!                         (<)
                Can you see it?             (<)
                                            (<)
            * item                          (<)
            * item                          (<)
            * item                          (<)
                                            (<)
            Here is (<)'inline code'(<).    (<)
                                            (<)
            ```cpp

            printf("Hello world");

            ```                             (<)
                                            (<)
            <p>(<)
                in tag block
            </p>(<)
                                            (<)
            AEIUEOAO!!                      (<)





    */
    //
    private static function SplitToChunk($plainText){
        
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

        $isInComment = false;

        // End 複数行にまたがって存在する情報 ----

        for($i = 0; $i < $lineCount; $i++){
            
            // --- コメントアウト処理 -------------
            if($isInComment){
                // コメントから出る
                if(preg_match("/^ *" . static::$commentEndToken . "/", $lines[$i], $matches) === 1){
                    $isInComment = false;
                    
                }
                continue;
            }
            else{
                // コメントに入る
                if(!$isInCodeBlock && $tagBlockLevel <= 0 &&
                   preg_match("/^ *" . static::$commentStartToken . "/", $lines[$i], $matches) === 1){
                    

                    if(!preg_match("/" . static::$commentEndToken . " *$/", $lines[$i], $matches)){
                        $isInComment = true;
                    }



                    continue;
                }
            }
            // end コメントアウト処理 -------------

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


            // 現在コードブロック内のとき
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

            // 現在コードブロックに入っていないとき
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

                // タグブロック内ではない
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


    private static function CreateChunk(){
        return ["indentLevel" => -1, "spaceCount" => 0, "isTagElement" => false, "isCodeBlock" => false,
         "content" => "", "nextLineChunkIndex"=>-1, "codeBlockAttribute" => "", "isInlineCode" => false];
    }



    

} // End class OutlineText



?>