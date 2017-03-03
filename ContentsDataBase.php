<?php
/*
 *ContentsDataBase
 *  最終更新日:
 *      1.15.2016
 *
 *  説明:
 *      Contentを管理するものです.
 *      Content情報を外部で使用できるようにAPIを提供します.
 *
 *  更新履歴:
 *      8.24.2016:
 *          プログラムの完成
 *
 *      8.25.2016:
 *          各ContentFile内のHTML要素内において改行, スペース, などの空文字は残すようにした
 *
 *      9.4.2016:
 *          最終更新日の表示を変更
 *
 *      9.9.2016:
 *          ContentのPathを絶対パスから相対パスに変更
 *
 *      10.5.2016:
 *          File名変更
 *
 *      10.6.2016:
 *          DebugLogで表示される日付の月と日が逆であった問題を修正
 *
 *      11.15.2016:
 *           関数'GetIndex'追加; このContentが何番目の子供か調べます
 *           ファイル読み込み時そのファイルがディレクトリであるか調べるようにした
 *
 *      12.2.2016:
 *          GetIndexの返り値として見つからない場合-1を返すようにした
 *
 *      1.15.2017:
 *          パフォーマンス向上
 *
 *      1.29.2017:
 *          新年になってログファイルが更新されない問題を修正
 *          セキュリティー強化
 */
if(isset($_POST["Command"]))
{
    ContentTransport::PerformCommand($_POST["Command"]);
}

//
//Type; Arguments...
//Type:
//  Content:
//  Error:
//
class ContentTransport
{
    //
    //関数:
    //  説明:
    //      Commandを解析し実行します.
    //
    //  返り値:
    //      true:
    //          成功
    //
    //      false:
    //          失敗
    //
    public static function  PerformCommand($command)
    {
        $work = json_decode($command, true);
        $header = $work["header"];


        switch($header)
        {
            case "GetContentFromFile":
                $filePath = $work["filePath"];
                $content = new Content();
                if($content->SetContent($filePath) === false)
                {
                    Debug::LogError("[PerformCommand] Fail > Command'GetContentFromFile'");
                    static::SendError("[PerformCommand] Fail. \nこのError情報は自動で管理者に送信されます.");
                    return false;
                }

                static::SendContent($content, 0);
                break;

            case "GetChild":
                $index = $work["index"];
                $contentData = $work["content"];

                $content = new Content();
                $content->DecodeFromContentData($contentData);
                $child = $content->GetChild($index);
                if($child === false)
                {
                    Debug::LogError("[PerformCommand] Fail > Command'GetChild'");
                    static::SendError("[PerformCommand] Fail. \nこのError情報は自動で管理者に送信されます.");
                    return false;
                }

                static::SendContent($child, $index);
                break;

            case "GetParent":
                $contentData = $work["content"];
                $content = new Content();
                $content->DecodeFromContentData($contentData);
                $parent = $content->GetParent();
                if($parent === false)
                {
                    Debug::LogError("[PerformCommand] Fail > Command'GetParent'");
                    static::SendError("[PerformCommand] Fail. \nこのError情報は自動で管理者に送信されます.");
                    return false;
                }

                static::SendContent($parent, 0);
                break;
        }

        return true;
    }

    //
    //関数
    //  説明:
    //      Client側にContentDataを送ります
    //
    public static function SendContent(Content $content, $index)
    {
        $data = $content->EncodeToContentData();
        $data += array("type" => "Content");
        $data += array("index" => $index);
        echo json_encode($data);
    }

    //
    //関数
    //  説明:
    //      Client側にErrorMessageを送ります
    //
    public static function SendError($message)
    {
        $data = array("type" => "Error", "message" => $message);
        echo json_encode($data);
    }


}

class Debug
{
    private static $logFileName = "OutputLog.txt";

    public static function Log($message)
    {
        //文字列に変換
        $messageStr = static::ToString($message);
        return static::OutputLog($messageStr);
    }

    public static function LogWarning($message)
    {
        //文字列に変換
        $messageStr = "WARNING: " . static::ToString($message);
        return static::OutputLog($messageStr);
    }

    public static function LogError($message)
    {
        //文字列に変換
        $messageStr = "ERROR: " . static::ToString($message);
        return static::OutputLog($messageStr);
    }

    private static function OutputLog($messageStr)
    {
        $renew = false;

        //Fileが存在するとき
        //Fileを新しく更新するかどうか判別
        if(file_exists(static::$logFileName))
        {
            $fdate = filemtime(static::$logFileName);
            if($fdate === false)
            {
                return false;
            }
            $fmonth = intval(date("n", $fdate));

            $date = getdate();
            $month = $date["mon"];
            if($month != $fmonth)
            {
                $renew = true;
            }
            else
            {
                $renew = false;
            }
        }

        //Fileを開く
        $file = null;
        if($renew)
        {
            $file = @fopen(static::$logFileName, "w");

            if($file === false)
            {
                return false;
            }
        }
        else
        {
            $file = @fopen(static::$logFileName, "a");
            if($file === false)
            {
                return false;
            }
        }

        //書き込み
        flock($file, LOCK_EX);
        fputs($file, "\r\n" . date("H:i:s; m.d.Y") . "\r\n" . $messageStr . "\r\n");
        flock($file, LOCK_UN);

        fclose($file);

        return true;
    }

    public static function ToString($object)
    {
        //nullのとき
        if(is_null($object))
        {
            return "null";
        }

        //stringのとき
        if(is_string($object))
        {
            return $object;
        }

        //数値のとき
        if(is_numeric($object))
        {
            return strval($object);
        }

        //boolのとき
        if(is_bool($object))
        {
            if($object === true)
            {
                return "true";
            }

            if($object === false)
            {
                return "false";
            }
        }

        //objectのとき
        if(is_object($object))
        {
            if(in_array("__toString", get_class_methods($object)))
            {
                return strval($object->__toString());
            }
            else
            {
                return get_class($object) . ": " . spl_object_hash($object);
            }
        }

        //配列のとき
        if(is_array($object))
        {
            return "Array";
        }

        //その他
        return strval($object);
    }
}

class Content
{
    //StartTag一覧
    private static $startTagNameList =
    [
        "Parent" => "<CDB_Parent>",
        "Children" => "<CDB_Children>",
        "Child" => "<CDB_Child>",
        "Title" => "<CDB_Title>",
        "Abstract" => "<CDB_Abstract>",
        "CreatedAt"=>"<CDB_CreatedAt>"
    ];

    //EndTag一覧
    private static $endTagNameList =
    [
        "Parent" => "</CDB_Parent>",
        "Children" => "</CDB_Children>",
        "Child" => "</CDB_Child>",
        "Title" => "</CDB_Title>",
        "Abstract" => "</CDB_Abstract>",
        "CreatedAt"=>"</CDB_CreatedAt>"
    ];

    private static $dateFormat = "Y/m/d";

    private $path = "";
    private $title = "";
    private $abstract = "";
    private $rootContent = "";

    //parentへのfilePath
    private $parent = "";

    //各childへのfilePathList
    private $children = array();

    private $isFinal = false;
    private $isRoot = false;
    private $updatedAt = "";
    private $createdAt = "";

    public function EncodeToContentData()
    {
        $data = array(
            "path"=> $this->path,
            "title" => $this->title,
            "abstract" => $this->abstract,
            "rootContent" => $this->rootContent,
            "parent" => $this->parent,
            "children" => $this->children,
            "isFinal" => $this->isFinal,
            "isRoot" => $this->isRoot,
            "updatedAt" => $this->updatedAt,
            "createdAt"=> $this->createdAt
            );

        return $data;
    }

    public function DecodeFromContentData($contentData)
    {
        $allTrue = true;

        $temp = $contentData["path"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'path'");
            $allTrue = false;
        }
        else
        {
            $this->path = $temp;
        }

        $temp = $contentData["title"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'title'");
            $allTrue = false;
        }
        else
        {
            $this->title = $temp;
        }

        $temp = $contentData["abstract"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'abstract'");
            $allTrue = false;
        }
        else
        {
            $this->abstract = $temp;
        }

        $temp = $contentData["rootContent"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'rootContent'");
            $allTrue = false;
        }
        else
        {
            $this->rootContent = $temp;
        }

        $temp = $contentData["parent"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'parent'");
            $allTrue = false;
        }
        else
        {
            $this->parent = $temp;
        }

        $temp = $contentData["children"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'children'");
            $allTrue = false;
        }
        else
        {
            $this->children = $temp;
        }


        $temp = $contentData["isFinal"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'isFinal'");
            $allTrue = false;
        }
        else
        {
            $this->isFinal = $temp;
        }

        $temp = $contentData["isRoot"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'isRoot'");
            $allTrue = false;
        }
        else
        {
            $this->isRoot = $temp;
        }

        $temp = $contentData["updatedAt"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'updatedAt'");
            $allTrue = false;
        }
        else
        {
            $this->updatedAt = $temp;
        }


        $temp = $contentData["createdAt"];
        if(is_null($temp))
        {
            Debug::LogWarning("[DecodeFromContentData] Fail > Key'createdAt'");
            $allTrue = false;
        }
        else
        {
            $this->createdAt = $temp;
        }
        return $allTrue;
    }

    //このContentがあったファイルのパスを取得
    public function GetPath()
    {
        return $this->path;
    }

    //Title(題名)取得
    public function GetTitle()
    {
        return $this->title;
    }

    //Abstract(概要)取得
    public function GetAbstract()
    {
        return $this->abstract;
    }

    //このContentが持つ子Contents取得
    public function GetChildren()
    {
        return $this->children;
    }

    //このContentが持つ子Contentsの数
    public function GetChildrenCount()
    {
        return count($this->children);
    }

    //このContentのRootContent取得
    public function GetRootContent()
    {
        return $this->rootContent;
    }

    //このContentが持つisFinal取得
    public function IsFinal()
    {
        return $this->isFinal;
    }

    //このContentが持つisRoot取得
    public function IsRoot()
    {
        return $this->isRoot;
    }

    //このContentが持つupdatedAt取得
    public function UpdatedAt()
    {
        return $this->updatedAt;
    }

    public function CreatedAt(){
        return $this->createdAt;
    }

    //このContentが何番目の子供か調べます
    public function GetIndex()
    {
        $parent = $this->GetParent();
        if($parent === false)
        {
            return -1;
        }
        $myIndex = -1;
        $brothers = $parent->GetChildren();
        $count = count($brothers);
        for($i = 0; $i < $count; $i++)
        {
            if($this->RelativePath(realpath(dirname($parent->path). "/" . $brothers[$i])) === $this->path)
            {
                $myIndex =$i;
                break;
            }
        }

        return $myIndex;
    }
    //
    //関数
    //  説明:
    //      このContentが含むChildを取得
    //
    //  返り値:
    //      content:
    //          取得したcontent
    //
    //      false:
    //          失敗
    //
    public function GetChild($index)
    {
        $childPath = $this->RelativePath(realpath(dirname($this->path) . "/" . $this->children[$index]));
        $child = new Content();
        if($child->SetContent($childPath) === false)
        {
            return false;
        }

        return $child;
    }

    //
    //関数:
    //  説明:
    //      このContentが持つParentを取得
    //
    //  返り値:
    //      content:
    //          取得したcontent
    //
    //      false:
    //          失敗
    //
    public function GetParent()
    {
        $parentPath = $this->RelativePath(realpath(dirname($this->path) . "/" . $this->parent));
        $parent = new Content();
        if($parent->SetContent($parentPath) === false)
        {
            return false;
        }

        return $parent;
    }


    //
    //関数:
    //  説明:
    //      fileを読み込みContent情報を設定します.
    //
    //  返り値:
    //      true:
    //          成功
    //
    //      false:
    //          失敗, Errorあり
    //
    function SetContent($filePath)
    {
        //拡張子確認
        $ext = substr($filePath, strrpos($filePath, '.') + 1);
        if($ext != "txt" && $ext != "html")
        {
            return false;
        }

        $data = $this->ReadFile($filePath);
        if($data === false)
        {
            return false;
        }
        $this->path = $filePath;
        $this->updatedAt = date(static::$dateFormat, filemtime($filePath));

        $dataList = $this->ToDataList($data);

        //Content情報を初期化
        $this->rootContent = "";
        $this->children = array();
        $this->parent = "";

        $hasParent = false;
        $hasChildren = false;
        $inChildren = false;
        $elements = array();
        $countDataList = count($dataList);
        for($i = 0; $i < $countDataList; $i++)
        {
            $str = $dataList[$i];
            /*
            //Debug
            Debug::Log($str);
             */
            switch($str)
            {
                case static::$startTagNameList["Children"]:
                    if($hasChildren)
                    {
                        Debug::LogError("[SetContent] Fail > file'{$filePath}'内に複数のCildren要素があります.");
                        return false;
                    }

                    $hasChildren = true;
                    $inChildren = true;
                    array_push($elements, array($str, ""));
                    break;

                case static::$startTagNameList["Child"]:
                    if(!$inChildren)
                    {
                        Debug::LogError("[SetContent] Fail > file'{$filePath}'内においてChildren要素外にChild要素があります.");
                        return false;
                    }
                    array_push($elements, array($str, ""));

                    break;

                case static::$startTagNameList["Parent"]:
                    $hasParent=true;
                    array_push($elements, array($str, ""));
                    break;

                case static::$startTagNameList["Title"]:
                case static::$startTagNameList["Abstract"]:
                case static::$startTagNameList["CreatedAt"]:
                    array_push($elements, array($str, ""));
                    break;

                case static::$endTagNameList["Children"]:
                    $element = array_pop($elements);
                    if($this->TagChecker($element[0], "Children") === false)
                    {
                        return false;
                    }
                    $inChildren = false;
                    break;

                case static::$endTagNameList["Child"]:
                    $element = array_pop($elements);
                    if($this->TagChecker($element[0], "Child") === false)
                    {
                        return false;
                    }
                    array_push($this->children, $element[1]);

                    break;

                case static::$endTagNameList["Title"]:
                    $element = array_pop($elements);
                    if($this->TagChecker($element[0], "Title") === false)
                    {
                        return false;
                    }
                    $this->title = $element[1];
                    break;

                case static::$endTagNameList["Abstract"]:
                    $element = array_pop($elements);
                    if($this->TagChecker($element[0], "Abstract") === false)
                    {
                        return false;
                    }
                    $this->abstract = $element[1];

                    break;

                case static::$endTagNameList["CreatedAt"]:
                    $element = array_pop($elements);
                    if($this->TagChecker($element[0], "CreatedAt") === false)
                    {
                        return false;
                    }

                    $this->createdAt = date(static::$dateFormat,strtotime($element[1]));

                    break;

                case static::$endTagNameList["Parent"]:
                    $element = array_pop($elements);
                    if($this->TagChecker($element[0], "Parent") === false)
                    {
                        return false;
                    }
                    $this->parent = $element[1];
                    break;

                default:
                    $countElements = count($elements);
                    if($countElements > 0)
                    {
                        $elements[$countElements - 1][1] .= $str;
                    }
                    else
                    {
                        $this->rootContent .= $str;
                    }

                    break;
            }

        }

        $this->isFinal = !$hasChildren;
        $this->isRoot = !$hasParent;
        return true;
    }


    //
    //関数:
    //  説明:
    //      File読み込みます
    //
    //  返り値:
    //      data:
    //          読み込んだdata
    //
    //      false:
    //          失敗
    //
    function ReadFile($filePath)
    {
        if(is_dir($filePath))
        {
            Debug::LogError("[ReadFile] Fail > Directory'{$filePath}'が読み込まれました.");
            return false;
        }

        //file読み込み
        $data = @file_get_contents($filePath);

        if($data === false)
        {
            Debug::LogError("[ReadFile] Fail > file'{$filePath}'の読み込みに失敗しました.");
            return false;
        }
        Debug::Log("[ReadFile] file'{$filePath}'を読み込みました.");

        return $data;
    }

    //DataをDataList-Tag分析されたList-に変換します
    function ToDataList($data)
    {
        //Tag分析
        $elements = array();
        $offset = 0;
        while(true)
        {
            $foundPos = false;
            $foundStr = "";
            foreach(static::$startTagNameList as $key=>$value)
            {
                //文字列検索
                $pos = strpos($data, $value, $offset);
                if($pos === false)
                {
                    continue;
                }

                //過去に見つかっている場所と比較して現在見つかった場所がそれよりも前にあるとき
                if($foundPos === false | $foundPos > $pos)
                {
                    $foundPos = $pos;
                    $foundStr = $value;
                }
            }

            foreach(static::$endTagNameList as $key=>$value)
            {
                //文字列検索

                $pos = strpos($data, $value, $offset);
                if($pos === false)
                {
                    continue;
                }

                //過去に見つかっている場所と比較して現在見つかった場所がそれよりも前にあるとき
                if($foundPos === false | $foundPos > $pos)
                {
                    $foundPos = $pos;
                    $foundStr = $value;
                }
            }

            //検索文字列がこれ以上見つからないとき
            if($foundPos === false)
            {
                //offsetからdataの最後まで読み込む
                array_push($elements, substr($data, $offset));
                break;
            }
            else
            {
                //offsetから見つけた位置まで読み込む
                array_push($elements, substr($data, $offset, $foundPos - $offset));
                $offset = $foundPos;

                //offsetから見つけた文字列の最後尾まで読み込む
                array_push($elements, substr($data, $offset, strlen($foundStr)));
                $offset += strlen($foundStr);
            }
        }

        foreach($elements as &$element){
            //要素前後にあるごみ―Space, 改行, タブなど―を排除
            $element = preg_replace('/^[\s]+/', '', $element);
            $element = preg_replace('/[\s]+$/', '', $element);
        }

        return $elements;
    }

    function TagChecker($startTag, $tagName)
    {
        if($startTag != static::$startTagNameList[$tagName])
        {
            Debug::LogError (
                "[TagChecker] Fail > StartTagとEndTagが一致しません. StartTag: " .
                $startTag .
                "; EndTag: " .
                static::$endTagNameList[$tagName] .
                ";");

            return false;
        }
    }


    function RelativePath($dst) {
        switch (false) {
            case $src = getcwd():
            case $dst = realpath($dst):
            case $src = explode(DIRECTORY_SEPARATOR, $src):
            case $dst = explode(DIRECTORY_SEPARATOR, $dst):
            case $src[0] === $dst[0]:
                return false;
        }
        $cmp =
            DIRECTORY_SEPARATOR === '\\' ?
            'strcasecmp' :
            'strcmp'
        ;
        for (
            $i = 0;
            isset($src[$i], $dst[$i]) && !$cmp($src[$i], $dst[$i]);
            ++$i
        );
        return implode(
            DIRECTORY_SEPARATOR,
            array_merge(
                array('.'),
                ($count = count($src) - $i) ?
                    array_fill(0, $count, '..') :
                    array()
                ,
                array_slice($dst, $i)
            )
        );
    }
}

?>