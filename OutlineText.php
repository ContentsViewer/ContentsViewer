<?php


class OutlineText{
    
    private static $indentSpace = 4;
    private static $blockTagList = [
        "script", "noscript",

        "p", "pre", "ol", "ul", "li", "dl", "dt", "dd", "figure", "figcaption", "div",

        "a", "em", "strong", "small", "s", "cite", "q", "i", "b", "span",

        "h1", "h2", "h3", "h4", "h5", "h6",

        "table", "caption", "tbody", "thead", "tr", "td", "th",

        "form", "input", "button", "textarea"

    ];

    //function __construct(){
        
    //}










    private static $patternForSplitBlock;
    private static $patternTagBlockStartTag;
    private static $patternTagBlockEndTag;


    public static function Init(){
    
        // ブロックごとに区切るためのpattern
        static::$patternForSplitBlock = "/";
        $blockTagCount = count(static::$blockTagList);
        for($i = 0; $i < $blockTagCount; $i++){
            static::$patternForSplitBlock .= "(<" . static::$blockTagList[$i] . "\b.*?>)|(<\/" . static::$blockTagList[$i] . " *?>)";
            if($i < $blockTagCount - 1){
                static::$patternForSplitBlock .= "|";
            }
        }

        static::$patternForSplitBlock .= "/i";




        static::$patternTagBlockStartTag = "/";
        $blockTagCount = count(static::$blockTagList);
        for($i = 0; $i < $blockTagCount; $i++){
            static::$patternTagBlockStartTag .=  "(<" . static::$blockTagList[$i] . "\b.*?>)";
            if($i < $blockTagCount - 1){
                static::$patternTagBlockStartTag .= "|";
            }
        }

        static::$patternTagBlockStartTag .= "/i";




        
        static::$patternTagBlockEndTag = "/";
        $blockTagCount = count(static::$blockTagList);
        for($i = 0; $i < $blockTagCount; $i++){
            static::$patternTagBlockEndTag .= "(<\/" . static::$blockTagList[$i] . " *?>)";
            if($i < $blockTagCount - 1){
                static::$patternTagBlockEndTag .= "|";
            }
        }

        static::$patternTagBlockEndTag .= "/i";



    }


    public static function Decode($text){
        $output = "";



        //$patternForSplitBlock ="/(<script>)|(</sript>)/i";
        //echo htmlspecialchars($patternForSplitBlock);

        $lines = explode("\n", $text);
        $lineCount = count($lines);



        // --- 複数行にまたがって存在する情報 -------------
        $indentLevelPrev = -1;
        $startSpaceCount = 0;
        $tagBlockLevel = 0;

        $isStartWriting = false;

        //$emptyLineCount = 0;

        $beginParagraph = false;
        $beginList = false;
        $beginTable = false;
        $tableColumnHeadCount = 0;
        $beginTableBody = false;

        // End 複数行にまたがって存在する情報 --------------

        // 各行ごとに対して
        for($i = 0; $i < $lineCount; $i++){

            // --- 行ごとの情報 ----------------
            
            $indentLevel = 0;
            $isEmpty = false;
            $spaceCount = 0;
            $beginSectionTitle = false;
            $beginListItem = false;
            $beginTableRow = false;

            // End 行ごとの情報 ------------
            
            // 書き込みの始まりを確認
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


            // すべて, Spaceのとき
            if($spaceCount == $wordCount){
                $isEmpty = true;
                //echo "em";
            }

            $indentLevel = ($spaceCount - $startSpaceCount) / static::$indentSpace;
            //echo $startSpaceCount;

            // End indentLevelの計算



            // 何もこの行に書かれていないとき
            if($isEmpty){
                if($tagBlockLevel > 0){
                    $output .= $lines[$i] . "\n";
                }    


                // TagBlock内でないとき
                else{
                    if($beginParagraph){
                        $output .= "</p>";
                        $beginParagraph = false;
                    }

                    if($beginList){
                        $output .= "</ul>";
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






            //echo $startSpaceCount;


            $blocks = preg_split(static::$patternForSplitBlock, $lines[$i],-1,PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            //var_dump($blocks);
            $blockCount = count($blocks);
            
            //Debug::Log($blocks);
                
            for($j = 0; $j < $blockCount; $j++){
                
                $isTag = false;


                if(preg_match(static::$patternTagBlockStartTag, $blocks[$j]) === 1){
                
                
                    $tagBlockLevel++;
                    $isTag = true;
                }

                if(preg_match(static::$patternTagBlockEndTag, $blocks[$j]) === 1){
                
                    $tagBlockLevel--;
                    $isTag = true;
                }

                

                // 処理対象のブロックがtagBlock内にあるもの, tagそのもののの場合は, 処理から外す.
                if($tagBlockLevel > 0 || $isTag){

                    $output .= $blocks[$j];
                    continue;
                }

                // --- ここから, PlainWritingのDecode処理が行われる.

                //Debug::Log( $blocks[$j]);
                
                // はじめのブロック; 行頭に対して
                if($j == 0){
                    
                    //// --- indentLevelの計算 -----------------------------
                    //$wordCount = strlen($blocks[$j]);
                    //$spaceCount = 0;
                    //for($spaceCount = 0; $spaceCount < $wordCount; $spaceCount++){
                    //    if($blocks[$j][$spaceCount] != ' '){
                    //        break;
                    //    }
                    //}


                    //// すべて, Spaceのとき
                    //if($spaceCount == $wordCount){
                    //    $emptyLineCount++;


                    //    if($beginParagraph){
                    //        $output .= "</p>";
                    //        $beginParagraph = false;

                    //    }


                    //    continue;
                    //}
                    

                    // spaceを取り除く
                    $blocks[$j] = substr($blocks[$j], $spaceCount);
                    
                    // spaceを取り除いて, 何も残らなかった場合
                    if(strlen($blocks[$j]) == 0){
                        continue;
                    }
                    //$indentLevel = ($spaceCount - $startSpaceCount) / static::$indentSpace;
                    //echo strlen($blocks[$j]) . ": " . $blocks[$j] . "<br>";

                    //echo $indentLevel;

                    // End indentの計算 ----------


                    $alreadySectionTagSetted = false;

                    if($indentLevel > $indentLevelPrev){
                        $levelDiff = $indentLevel - $indentLevelPrev;

                        while($levelDiff > 0){
                            $output .= "<div class='Section'>";
                            $levelDiff--;
                        }
                        
                    }

                    if($indentLevel < $indentLevelPrev){
                        $levelDiff = $indentLevelPrev - $indentLevel;

                        while($levelDiff > 0){
                            $output .= "</div>";
                            $levelDiff--;
                        }
                    }

                    $ret = 0;
                    $matches = array();
                    if(preg_match("/^#/", $blocks[$j]) === 1){
                        //if(!$alreadySectionTagSetted && $indentLevel > $indentLevelPrev){
                        //    $output .= "<ul class='SectionList'> <li>";
                        //    $alreadySectionTagSetted = true;
                        //}

                        //if($indentLevel == $indentLevelPrev){
                        //    $output .= "</li><li>";
                        //}

                        //if(!$alreadySectionTagSetted && $indentLevel < $indentLevelPrev){
                        //    $output .= "</li> </ul> <li>";
                        //    $alreadySectionTagSetted = true;
                        //}
                        //$output .= "<h" . ($indentLevel + 2) . ">" . substr($blocks[$j], 1) . "</h". ($indentLevel + 2) . ">";
                        $blocks[$j] = substr($blocks[$j], 1);

                        
                        $output .= "<h" . ($indentLevel + 2) . " ";
                        if($indentLevel <= 0){
                            $output .= "class = 'SectionTitle'>";
                        }
                        else{
                            $output .= "class = 'SubSectionTitle'>";
                        }


                        $beginSectionTitle = true;
                    }
                    elseif(preg_match("/^\*/", $blocks[$j]) === 1){


                        $blocks[$j] = substr($blocks[$j], 1);

                        if(!$beginList){
                            $output .= "<ul>";
                            $beginList = true;
                        }

                        $output .= "<li>";
                        $beginListItem = true;
                    }
                    elseif(preg_match("/^!\[(.*)?\]\((.*)?\)/", $blocks[$j],$matches)){
                        //$temp = substr($blocks[$j], 0, $matches[0][1]);
                        $temp = "<figure><img src='" . $matches[2] . "' alt='". 
                                   $matches[1] ."'/><figcaption>" . $matches[1] . "</figcaption></figure>";
                        //$temp .= substr($blocks[$j], $matches[0][1] + strlen($matches[0][0]));
                        
                        $blocks[$j] = $temp;
                    }
                    elseif(($ret = static::CheckTableLine($blocks[$j], $matches)) != -1){
                    
                        $temp = "";

                        if(!$beginTable){
                            $output .= "<table>";

                            if($ret == -3){
                                $output .= "<caption>" . $matches[0] . "</caption>";
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


                                $temp .= $matches[$k];

                                if( ($beginTableBody && $k < $tableColumnHeadCount)  || !$beginTableBody){
                                    $temp .= "</th>";
                                }
                                else {
                                    $temp .= "</td>";
                                }
                            }

                        }

                        
                        $blocks[$j] = $temp;
                    }
                    else {
                        if(!$beginParagraph){
                            
                            //if(!$alreadySectionTagSetted && $indentLevel > $indentLevelPrev){
                            //    $output .= "<ul class='SectionList'> <li>";
                            //    $alreadySectionTagSetted = true;
                            //}
                            
                            //if(!$alreadySectionTagSetted && $indentLevel < $indentLevelPrev){
                            //    $output .= "</li> </ul>";
                            //    $alreadySectionTagSetted = true;
                            //}

                            $output .= "<p>";
                            $beginParagraph = true;


                        }
                    }



                    //if($indentLevel != $indentLevelPrev){
                    //    if($indentLevel > $indentLevelPrev){
                    //        $output .= "<ul class='SectionList'> <li>";
                    //    }    
                    //    else {
                    //        $output .= "</li> </ul>";
                    //    }
                    //    $indentLevelPrev = $indentLevel;
                    //}

                    $indentLevelPrev = $indentLevel;

                } // End 行頭処理 -----------

                
                //echo $blocks[$j];

                

                // 行内token処理

                $matches = array();

                if(preg_match("/\[(.*)?\]\((.*)?\)/", $blocks[$j],$matches, PREG_OFFSET_CAPTURE)){
                    $temp = substr($blocks[$j], 0, $matches[0][1]);
                    $temp .= "<a href='" . $matches[2][0] . "'>" . $matches[1][0] . "</a>";
                    $temp .= substr($blocks[$j], $matches[0][1] + strlen($matches[0][0]));
                        
                    $blocks[$j] = $temp;
                }




                $output .= $blocks[$j];

            }

            if($beginSectionTitle){
                $output .= "</h" . ($indentLevel + 2) . ">";
            }

            if($beginListItem){
            
                $output .= "</li>";
            }

            if($beginTableRow){
                $output .= "</tr>";
            }


            $output .= "\n";

        } // End 各行ごとに対して
        

        if($beginParagraph){
            $output .= "</p>";
        }

        if($beginList){
            $output .= "</ul>";
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
        while($indentLevelPrev >= 0){
            $output .= "</div>";
            $indentLevelPrev--;
        }
        //Debug::Log($output);

        return $output;
    }

    //
    // @return:
    //  -1: Not table
    //  -2: It's a mark of the begining of the tableBody
    //  -3: It's a table caption.
    //  over zero: column head count
    private static function CheckTableLine($line, &$matches){
        $matches = [];

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
        return $columnHeadCount;

    }

    

} // End class PlainWriting


?>