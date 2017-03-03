<!-- 
最終更新日:
    2.18.2017

説明:
    ContentDataBaseが持つContent情報をWebPageにします.
    ContentsViewerのスタンダードデザイン

更新履歴:
    8.24.2016:
        プログラムの完成

    10.5.2016:
        スクリプト修正

    10.7.2016:
        WebPageViewer.js修正に伴う修正

    11.15.2016:
        Ajaxを用いないで描画するようにした; SEO対策
        同階層にある左右の兄弟に直接アクセスできるようにした
        親コンテンツを三つまで直接アクセスできるようにした

    12.2.2016:
        ContetnsDataBase更新に伴う修正

    2.18.2017:
        QuickLookModeを追加
        名前の変更
-->

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8" />
    <link rel="shortcut icon" href="Common/favicon.ico" type="image/vnd.microsoft.icon" />
    <!-- ビューポートの設定 -->
    <meta name="viewport" content="width=device-width,initial-scale=1" />

    <link rel="stylesheet" href="ContentsViewerStandard.css" />
    <link rel="stylesheet" href="GUILayout.css" />
    <script src="GUILayout.js"></script>
    <script src="ContentsViewerStandard.js"></script>
    <?php
    include "ContentsDataBase.php";

    $rootContentPath = 'Contents/Root.html';
    $contentsDataBaseURL = 'ContentsDataBase.php';
    $parentsMaxCount = 3;
    $brotherTitleMaxStrWidth = 20;

    $key =$rootContentPath;
    if(isset($_GET['contentPath']))
    {
        $key = $_GET['contentPath'];
    }
    $currentContent = new Content();
    $parents = [];
    $children = [];
    $leftContent = null;
    $rightContent = null;


    $isGetCurrentContent = true;
    //CurrentContentの取得
    if($currentContent->SetContent($key))
    {
        $isGetCurrentContent = true;

        //ChildContentsの取得
        $childrenPathList = $currentContent->GetChildren();
        for($i = 0; $i < count($childrenPathList); $i++)
        {
            array_push($children, $currentContent->GetChild($i));
        }

        //Parentsの取得
        $parent = $currentContent->GetParent();
        for($i = 0; $i < $parentsMaxCount; $i++)
        {
            if($parent === false)
            {
                break;
            }
            array_push($parents, $parent);
            $parent = $parent->GetParent();
        }

        //LeftContent, RightContentの取得
        if(isset($parents[0]))
        {
            $parent = $parents[0];
            $brothers = $parent->GetChildren();
            $myIndex = $currentContent->GetIndex();
            if($myIndex >= 0)
            {
                if($myIndex > 0)
                {
                    $leftContent = $parent->GetChild($myIndex - 1);
                }
                if($myIndex <  count($brothers) - 1)
                {
                    $rightContent = $parent->GetChild($myIndex + 1);
                }
            }
        }

        //title作成
        $title = "";
        if(isset($parents[0]))
        {
            $title .= $parents[0]->GetTitle() . "/";
        }
        $title .= $currentContent->GetTitle();

        echo "<title>".$title."</title>";
    }
    else
    {
        $isGetCurrentContent = false;
        echo "<title>NotExist</title>";
    }
    ?>

    <?php
    readfile("Common/CommonHead.html");
    ?>
</head>
<body>
    <div id="HeaderArea">ContentsViewer</div>
    <?php

    //===コード==================================================

    //CurrentContentを取得したかどうか
    if(!$isGetCurrentContent)
    {
        echo '<div id="ErrorMessageBox">';
        echo  '<h1>Error!</h1> <br>存在しないContentにアクセスした可能性があります.';
        echo '</div>';
        exit();
    }
    //---Navigator作成---------------------------------------------------------------------------------------------------
    $navigator = "";
    $navigator.= "<ul class='Navi'>";
    CreateNavHelper($parents, count($parents)- 1, $currentContent, $children, $navigator);
    $navigator.= "</ul>";


    //---LeftSideArea----------------------------------------------------------------------------------------------------

    echo "<div id ='LeftSideArea'>";
    echo $navigator;
    echo "</div>";


    //---MainArea--------------------------------------------------------------------------------------------------------
    echo '<div id="MainArea">';

    //最終更新欄
    echo '<div class="FileDateField">';
    echo "<img src='Common/CreatedAtStampA.png' alt='公開日'>: ". $currentContent->CreatedAt() 
        . " <img src='Common/UpdatedAtStampA.png' alt='更新日'>: " . $currentContent->UpdatedAt();
    echo '</div>';

    //概要欄
    echo '<div id="AbstractField" class="Abstract">';
    echo $currentContent->GetAbstract();
    echo '</div>';

    //本編
    echo '<div id="RootContentField" class="RootContent">';
    echo $currentContent->GetRootContent();
    echo '</div>';

    //子コンテンツ
    echo '<div id="ChildrenField">';
    for($i = 0; $i < count($children); $i++)
    {
        if($children[$i]===false)
        {
            echo '<p class="LinkButtonBlock">Error; 存在しないコンテンツです</p>';
        }
        else
        {

            echo "<div style='width:100%; display: table'>";
            echo "<div style='display: table-cell; width: 90%;'>";

            echo '<a class="LinkButtonBlock" href ="'.CreateHREF($children[$i]->GetPath()).'" ">';
            echo $children[$i]->GetTitle();
            echo '</a>';

            echo "</div><div class='ChildDetailButton' style='display:table-cell;  width:10%;' "
            .'onmouseover="QuickLookMouse('
            ."'ChildContent"
            .$i
            ."')"
            .'" '
            .'ontouchstart="QuickLookTouch('
            ."'ChildContent"
            .$i
            ."')"
            .'" '
            .'onmouseout="ExitQuickLookMouse()" '
            .'ontouchend="ExitQuickLookTouch()"'
            ."></div>";

            echo "<div class='ContentContainer' "
            ."id='ChildContent".$i."Container"."'"
            .">";
            echo "<h1>" . $children[$i]->GetTitle(). "</h1>";
            echo "<p>" . $children[$i]->GetAbstract(). "</p>";
            echo "<p>" . $children[$i]->GetRootContent(). "</p>";
            echo "</div>";

            echo "</div>";
        }
    }
    echo '</div>';

    //---MainPageBottomAppearingOnSmallScreen----------------------------------
    echo "<div id='MainPageBottomAppearingOnSmallScreen'>";

    echo $navigator;

    echo "</div>";
    echo '</div>';

    //---TopArea--------------------------------------------------------------------------------------------------------
    echo '<div id="TopArea">';

    //親コンテンツ
    echo '<div id="ParentField" class="ParentField">';
    for($i = 0; $i < count($parents); $i++)
    {
        $index = count($parents) - $i - 1;
        if($parents[$index]===false)
        {
            echo '<p class="LinkButtonBlock">Error; 存在しないコンテンツです</p>';
        }
        else
        {
            echo '<a  href ="'.CreateHREF($parents[$index]->GetPath()).'">';
            echo $parents[$index]->GetTitle();
            echo '</a>';
        }
        echo ' > ';
    }
    echo '</div>';

    //タイトル欄
    echo '<div id="TitleField" class="Title">';
    echo $currentContent->GetTitle();
    echo '</div>';

    echo' </div>';

    //---BottomRightArea----------------------------------------------------------------------------------------------

    if(!is_null($rightContent))
    {
    ?>
    <div id="BottomRightArea"
        onmouseover="QuickLookMouse('RightContent')"
        onmouseout="ExitQuickLookMouse()"
        ontouchstart="QuickLookTouch('RightContent')"
        ontouchend="ExitQuickLookTouch()">

        <?php
        if($rightContent===false)
        {
            echo "Error; 存在しないコンテンツです >";
        }
        else
        {
            echo '<a  href ="'.CreateHREF($rightContent->GetPath()).'">';
            echo  mb_strimwidth($rightContent->GetTitle(), 0, $brotherTitleMaxStrWidth, "...", "UTF-8") . " >";
            echo '</a>';
            echo "<div id = 'RightContentContainer' class='ContentContainer'>";
            echo "<h1>" . $rightContent->GetTitle(). "</h1>";
            echo "<p>" . $rightContent->GetAbstract(). "</p>";
            echo "<p>" . $rightContent->GetRootContent(). "</p>";
            echo "</div>";
        }
        ?>
    </div>
    <?php
    }

    //---BottomLeftArea------------------------------------------------------------------------------------------------
    if(!is_null($leftContent))
    {
    ?>
    <div id="BottomLeftArea"
        onmouseover="QuickLookMouse('LeftContent')"
        onmouseout="ExitQuickLookMouse()"
        ontouchstart="QuickLookTouch('LeftContent')"
        ontouchend="ExitQuickLookTouch()">

        <?php
        if($leftContent===false)
        {
            echo "Error; 存在しないコンテンツです >";
        }
        else
        {
            echo '<a  href ="'.CreateHREF($leftContent->GetPath()).'">';
            echo  "< ". mb_strimwidth($leftContent->GetTitle(), 0, $brotherTitleMaxStrWidth, "...", "UTF-8");
            echo '</a>';
            echo "<div id = 'LeftContentContainer' class='ContentContainer'>";
            echo "<h1>" . $leftContent->GetTitle(). "</h1>";
            echo "<p>" . $leftContent->GetAbstract(). "</p>";
            echo "<p>" . $leftContent->GetRootContent(). "</p>";
            echo "</div>";
        }
        ?>
    </div>
    <?php
    }
    //-----QuickLookArea---------------------------------------------------------
    ?>
    <div id='QuickLookArea'></div>

    <?php
    //contentPathからaタグで用いられる参照を返します
    function CreateHREF($contentPath)
    {
        return '/?contentPath=' . $contentPath;
    }

    function CreateNavHelper(&$parents, $parentsIndex, &$currentContent, &$children,  &$navigator){
        if($parentsIndex < 0){

            $navigator.=  "<li>";
            $navigator.=  "<a class = 'Selected' href='" . CreateHREF($currentContent->GetPath()) . "'>" . $currentContent->GetTitle() . "</a>";
            $navigator.=  "</li>";

            $navigator.="<ul>";
            foreach($children as $c){

                $navigator.=  "<li>";
                $navigator.=  "<a href='" . CreateHREF($c->GetPath()) . "'>" . $c->GetTitle() . "</a>";
                $navigator.=  "</li>";
            }
            $navigator.="</ul>";

            return;
        }

        $childrenCount = $parents[$parentsIndex]->GetChildrenCount();

        $navigator.=  "<li>";
        $navigator.=  "<a class = 'Selected' href='" . CreateHREF($parents[$parentsIndex]->GetPath()) . "'>" . $parents[$parentsIndex]->GetTitle() . "</a>";
        $navigator.=  "</li>";

        $navigator.=  "<ul>";
        if($parentsIndex == 0){
            $currentContentIndex = $currentContent->GetIndex();
            for($i = 0; $i < $childrenCount; $i++){

                $child = $parents[$parentsIndex]->GetChild($i);

                if($i == $currentContentIndex){

                    $navigator.=  "<li>";
                    $navigator.=  "<a class = 'Selected' href='" . CreateHREF($child->GetPath()) . "'>" . $child->GetTitle() . "</a>";
                    $navigator.=  "</li>";

                    $navigator.="<ul>";
                    foreach($children as $c){

                        $navigator.=  "<li>";
                        $navigator.=  "<a href='" . CreateHREF($c->GetPath()) . "'>" . $c->GetTitle() . "</a>";
                        $navigator.=  "</li>";
                    }
                    $navigator.="</ul>";
                }
                else{
                    $navigator.=  "<li>";
                    $navigator.=  "<a href='" . CreateHREF($child->GetPath()) . "'>" . $child->GetTitle() . "</a>";
                    $navigator.=  "</li>";
                }
            }
        }
        else{
            $nextParentIndex = $parents[$parentsIndex - 1]->GetIndex();
            for($i = 0; $i < $childrenCount; $i++){

                if($i == $nextParentIndex){
                    CreateNavHelper($parents, $parentsIndex-1, $currentContent, $children, $navigator);
                }
                else{
                    $child = $parents[$parentsIndex]->GetChild($i);
                    $navigator.=  "<li>";
                    $navigator.=  "<a href='" . CreateHREF($child->GetPath()) . "'>" . $child->GetTitle() . "</a>";
                    $navigator.=  "</li>";
                }
            }
        }
        $navigator.=  "</ul>";
        return;
    }
    ?>
</body>
</html>
