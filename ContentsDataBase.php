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


 include "Debug.php";



class Content
{

    private static $tagMap =
    [
        "Header" => ["StartTag" => "<Header>", "EndTag" => "</Header>"],
        "Parent" => ["StartTag" => "<Parent>", "EndTag" => "</Parent>"],
        "Child" => ["StartTag" => "<Child>", "EndTag" => "</Child>"],
        "Title" => ["StartTag" => "<Title>", "EndTag" => "</Title>"],
        "CreatedAt" => ["StartTag" => "<CreatedAt>", "EndTag" => "</CreatedAt>"],
        "Summary" => ["StartTag" => "<Summary>", "EndTag" => "</Summary>"]
    ];


    private static $dateFormat = "Y/m/d";

    private static $contentFileExtension = ".content";

    // コンテンツファイルへのパス.
    private $path = "";
    private $title = "";
    private $summary = "";
    private $body = "";
    private $updatedAt = "";
    private $createdAt = "";

    //parentへのfilePath
    private $parentPath = "";

    //各childへのfilePathList
    private $childPathList = array();



    //このContentがあったファイルのパスを取得
    public function Path()
    {
        return $this->path;
    }

    //Title(題名)取得
    public function Title()
    {
        return $this->title;
    }

    //概要取得
    public function Summary()
    {
        return $this->summary;
    }

    public function SetSummary($summary){
        return $this->summary = $summary;
    }

    //このContentが持つ子Contents取得
    public function ChildPathList()
    {
        return $this->childPathList;
    }

    public function ParentPath(){
        return $this->parentPath;
    }

    //このContentが持つ子Contentsの数
    public function ChildCount()
    {
        return count($this->childPathList);
    }

    //このContentのRootContent取得
    public function Body()
    {
        return $this->body;
    }
    public function SetBody($body){
        return $this->body = $body;
    }


    //このContentが末端コンテンツかどうか
    public function IsFinal()
    {
        return count($this->childPathList) == 0;
    }

    //このContentが最上位コンテンツかどうか
    public function IsRoot()
    {
        return $this->parentPath == "";
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
    public function ChildIndex()
    {
        $parent = $this->Parent();
        if($parent === false)
        {
            return -1;
        }
        $myIndex = -1;
        $brothers = $parent->ChildPathList();
        $count = count($brothers);
        for($i = 0; $i < $count; $i++)
        {
            if($this->RelativePath(realpath(dirname($parent->path . static::$contentFileExtension). "/" . $brothers[$i] . static::$contentFileExtension)) === $this->path . static::$contentFileExtension)
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
    public function Child($index)
    {
        $childPath = $this->RelativePath(realpath(dirname($this->path . static::$contentFileExtension) . "/" . $this->childPathList[$index] . static::$contentFileExtension));
        $child = new Content();
        if($child->SetContent(substr($childPath, 0, strrpos($childPath, '.'))) === false)
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
    public function Parent()
    {
        $parentPath = $this->RelativePath(realpath(dirname($this->path . static::$contentFileExtension) . "/" . $this->parentPath . static::$contentFileExtension));
        $parent = new Content();
        if($parent->SetContent(substr($parentPath, 0, strrpos($parentPath, '.'))) === false)
        {
            return false;
        }

        return $parent;
    }



    //
    // fileを読み込みContentの情報を設定します.
    // @return:
    //  true: 成功
    //  false: 失敗
    function SetContent($filePath)
    {
        //拡張子確認
        //$ext = substr($filePath, strrpos($filePath, '.') + 1);
        //if($ext != static::$contentFileExtension)
        //{
        //    return false;
        //}
        
        $filePath .= static::$contentFileExtension;
        

        //パス正規化
        $filePath = $this->RelativePath(realpath($filePath));
        
        // echo $filePath;
        $text = $this->ReadFile($filePath);
        if($text === false)
        {
            return false;
        }

        // 拡張子を除くPathを保存
        $this->path = substr($filePath, 0, strrpos($filePath, '.'));
        //$this->path = $filePath;
        $this->updatedAt = date(static::$dateFormat, filemtime($filePath));

        //$dataList = $this->ToDataList($data);

        //Content情報を初期化
        $this->body = "";
        $this->childPathList = array();
        $this->parentPath = "";



        $lines = explode("\n", $text);
        $lineCount = count($lines);

        $isInHeader = false;
        $isInSummary = false;

        // 各行ごとの処理
        for($i = 0; $i < $lineCount; $i++){
            
            if($isInHeader){
                // Header内にある場合はHeaderの終了タグを検索する.
                if(strpos($lines[$i], static::$tagMap['Header']['EndTag']) !== false){
                    $isInHeader = false;
                    continue;
                }
            }

            else{
                // Header内にないときはHeaderの開始タグを検索する.
                if(strpos($lines[$i], static::$tagMap['Header']['StartTag']) !== false){
                    $isInHeader = true;
                    continue;
                    
                }
            }

            // Header内
            if($isInHeader){
            
                if($isInSummary){
                    if(strpos($lines[$i], static::$tagMap['Summary']['EndTag']) !== false){
                        $isInSummary = false;
                        continue;
                    }
                }


                else{
                

                    $position = 0;

                    if(($position = strpos($lines[$i], static::$tagMap['Parent']['StartTag'])) !== false){
                        $position += strlen(static::$tagMap['Parent']['StartTag']);

                        $this->parentPath = substr($lines[$i], $position);
                        $this->parentPath = str_replace(" ", "", $this->parentPath);

                        continue;
                    
                    } elseif(($position = strpos($lines[$i], static::$tagMap['Child']['StartTag'])) !== false){
                        $position += strlen(static::$tagMap['Child']['StartTag']);
                        
                        $childPath = substr($lines[$i], $position);
                        $childPath = str_replace(" ", "", $childPath);

                        $this->childPathList[] = $childPath;
                        
                        continue;

                    } elseif(($position = strpos($lines[$i], static::$tagMap['CreatedAt']['StartTag'])) !== false){
                        $position += strlen(static::$tagMap['CreatedAt']['StartTag']);
                        
                        $this->createdAt = substr($lines[$i], $position);
                        $this->createdAt = str_replace(" ", "", $this->createdAt);

                        continue;

                     } elseif(($position = strpos($lines[$i], static::$tagMap['Title']['StartTag'])) !== false){
                        $position += strlen(static::$tagMap['Title']['StartTag']);
                        
                        $this->title = substr($lines[$i], $position);

                        continue;

                    } elseif(($position = strpos($lines[$i], static::$tagMap['Summary']['StartTag'])) !== false){
                        $isInSummary = true;
                        continue;

                    }
                }


                if($isInSummary){
                    $this->summary .= $lines[$i] . "\n";
                }
            } // End Header内

            else{
                $this->body .= $lines[$i] . "\n";
            }
        }



        return true;
    }


    //
    // ファイルを読み込みます
    //
    // @param filePath:
    //  読み込み先
    //
    // @return:
    //  読み込んだ文字列を返します. 失敗した場合はfalseを返します.
    //
    static function ReadFile($filePath)
    {
       
        if(is_dir($filePath))
        {
            Debug::LogError("[ReadFile] Fail > Directory'{$filePath}'が読み込まれました.");
            return false;
        }

        //file読み込み
        $text = @file_get_contents($filePath);

        // Unix処理系の改行コード(LF)にする.
        $text = str_replace("\r", "", $text);

        if($text === false)
        {
            Debug::LogError("[ReadFile] Fail > file'{$filePath}'の読み込みに失敗しました.");
            return false;
        }
        //Debug::Log("[ReadFile] file'{$filePath}'を読み込みました.");

        return $text;
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