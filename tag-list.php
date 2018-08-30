<?php

include "ContentsViewerUtil.php";
include "ContentsDatabase.php";


Content::LoadGlobalTagMapMetaFile();

$tagMap = Content::GlobalTagMap();

$tagMapCount = count($tagMap);


$tagName = "";
$detailMode = false;
if(isset($_GET['name']))
{
    $tagName = $_GET['name'];

    if(array_key_exists($tagName, $tagMap)){
        $detailMode = true;
    }
}

$content = new Content();
$contentTitlePathMap = array();
if($detailMode){
    foreach($tagMap[$tagName] as $path){
        if($content->SetContent($path)){
            $contentTitlePathMap[$content->Title()] = $path;
        }
    }

}

$tagIndexListElement = CreateTagIndexListElement($tagMap, $tagName);

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8" />
    <link rel="shortcut icon" href="Common/favicon.ico" type="image/vnd.microsoft.icon" />
    <!-- ビューポートの設定 -->
    <meta name="viewport" content="width=device-width,initial-scale=1" />

    <link rel="stylesheet" href="ContentsViewerStandard.css" />
    <script src="ContentsViewerStandard.js"></script>
    
    <?php
    $pageTitle = "";

    if($detailMode){
        $pageTitle = $tagName;
    }
    else{

        $pageTitle = "タグ一覧";
    }

    echo "<title>" . $pageTitle . "</title>";

    readfile("Common/CommonHead.html");
    ?>



</head>


<body>

    <div id="HeaderArea">
        <a href="./?content=./Contents/Root">ContentsViewer</a>
    </div>

    <div id ='LeftSideArea'>
        <div class="Navi">
            <?php
            echo $tagIndexListElement;
            ?>
        </div>
    </div>


    <div id = 'RightSideArea'>
        Index
        <div class='Navi'>
            <?php
            if($detailMode){
                echo "<ul>";
                foreach($contentTitlePathMap as $title => $path){
                    echo "<li><a href='" . CreateContentHREF($path) . "'>" . $title . "</a></li>";
                }
                echo "</ul>";
            }
            else{
                echo "目次がありません";
            }
            ?>
        </div>

    </div>
    <div id="MainArea">
        <?php

        echo '<div id="SummaryField" class="Summary">';
        echo CreateNewBox($tagMap);

        echo "<h2>タグ一覧</h2>";
        echo CreateTagListElement($tagMap);
        
        echo "</div>";

        
        echo '<div id="ChildrenField">';
        foreach($contentTitlePathMap as $title => $path)
        {
            echo "<div style='width:100%; display: table'>";

            echo "<div style='display: table-cell'>";

            echo '<a class="LinkButtonBlock" href ="'.CreateContentHREF($path).'">';
            echo $title;
            echo '</a>';

            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        ?>

    </div>

    <div id='MainPageBottomAppearingOnSmallScreen'>
        <div class="Navi">
            <?php
            echo $tagIndexListElement;
            ?>
        </div>

    </div>
    <div id="TopArea">

        <div id="ParentField" class="ParentField">
            <?php
            if($detailMode){
                echo "<a href='" . CreateTagDetailHREF("") . "'>タグ一覧</a>"; 
            }
            ?>
        </div>


        <div id="TitleField" class="Title">
            <?php
            echo $pageTitle;
            ?>
        </div>
    </div>

    
</body>




</html>

