<?php
function CreateContentHREF($contentPath)
{
    return '/?content=' . $contentPath;
}


function CreateTagDetailHREF($tagName){
    
    // $url  = empty($_SERVER["HTTPS"]) ? "http://" : "https://";
    // $url .= $_SERVER["HTTP_HOST"]."/tag-list.php";
    return  '/tag-list.php?name=' . $tagName;
}




function CreateTagIndexListElement($tagMap, $selectedTagName){
    $listElement = "<ul>";
    foreach($tagMap as $name => $pathList){
        
        $selectedStr = "";
        if($name == $selectedTagName){
            $selectedStr = " class='Selected' ";
        }
        $listElement .= "<li><a href='" . CreateTagDetailHREF($name) .  "'" .  $selectedStr .">" . $name . "</a></li>";
        
    }
    $listElement .= "</ul>";

    return $listElement;
}


function CreateNewBox($tagMap){
    $newBoxElement = "<div class='new-box'><ol class='new-list'>";
    
    if(array_key_exists("New", $tagMap)){
        $newPathList = $tagMap["New"];
        $newPathListCount = count($newPathList);
    
        $content = new Content();
        for($i = 0; $i < $newPathListCount; $i++){
            if($content->SetContent($newPathList[$i])){
    
                $title = "[" . $content->UpdatedAt() . "] " . $content->Title();
                $newBoxElement .= "<li><a href='" . CreateContentHREF($content->Path()) . "'>" . $title . "</a></li>";
            }
        }
            
    
    }
    
    $newBoxElement .= "</ol></div>";

    return $newBoxElement;
}


function CreateTagListElement($tagMap){
    $listElement = "<ul class='tag-list'>";

    foreach($tagMap as $name => $pathList){
        $listElement .= "<li><a href='" . CreateTagDetailHREF($name) . "'>" . $name . "<span>" . count($pathList) . "</span></a></li>";
    }
    $listElement .= "</ul>";

    return $listElement;
}
?>