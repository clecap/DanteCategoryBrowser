<?php

class CategoryGraph {

// that is the main function where we enter
public static function onCategoryPageView( &$cat ) { 
  $title = $cat->getTitle();
  $dbKey = $title->getDBkey();

  $dot = self::doQuery($title);
  self::doDot($dbKey, $dot);
  self::showImg($dbKey);
 }



static  private function getSQLCategories( $title = null ) {  // Constructs SQL statement to select categories related to title.
    global $wgOut;
 
    $id   = $title->getArticleID();
    $text = $title->getDBkey();
 
    $NScat = NS_CATEGORY;
    $dbr = wfGetDB(DB_REPLICA);
    // Use the following for MediaWiki 1.9:
    $text = $dbr->addQuotes($text);
    //$text = "'".wfStrencode($text)."'"; 
 
    $categorylinks = $dbr->tableName('categorylinks');
    $page          = $dbr->tableName('page');
    $sql =
      "SELECT\n".
      "    page_title AS cat,\n".
      "    page_is_redirect AS redirect,\n".
      "    0                AS missing\n".
      "  FROM $page as a\n".
      "  left JOIN $categorylinks as b\n".
      "  ON a.page_id=b.cl_from\n".
      "  left join $categorylinks as c\n".
      "  ON a.page_title=c.cl_to\n".
      "  WHERE\n".
      "    page_namespace = {$NScat} AND\n".
      "  (  c.cl_from    = $id OR\n".
      "     a.page_id    = $id OR\n".
      "     b.cl_to      = {$text} )\n".
      "UNION\n".
      "SELECT\n".
      "    cl_to as cat,\n".
      "    0 AS redirect,\n".
      "    1 AS missing\n".
      "  FROM $categorylinks\n".
      "  LEFT JOIN $page\n".
      "  ON page_title=cl_to\n".
      "  WHERE\n".
      "    page_id IS NULL";


  // cl_to:    name of the category to which the link is pointing to
  // cl_fom:   page id of the page which contains the category link

  // missing is true for all categories, which are employed in category links but do not have a category page describing it


  // $wgOut->addHTML("<pre>$sql</pre>");
  return $sql;
}

static private function getSQLCategoryLinks( $title ) { // Constructs SQL statement to select links between categories.
  global $wgOut;
 
  $id    = $title->getArticleID();
  $text  = $title->getDBkey();
  $NScat = NS_CATEGORY;
  $dbr   = wfGetDB(DB_REPLICA);

  // Use the following for MediaWiki 1.9:
  $text = $dbr->addQuotes($text);
  // $text = "'".wfStrencode($text)."'"; 
 
  $categorylinks = $dbr->tableName('categorylinks');
  $page          = $dbr->tableName('page');
  $sql =
      "SELECT\n".
      "    page_title AS cat_from, \n".
      "    cl_to as cat_to\n".
      "  FROM $page\n".
      "  INNER JOIN $categorylinks\n".
      "  ON page_id=cl_from\n".
      "  WHERE\n".
      "    ( page_id=$id  OR\n".
      "    cl_to=$text ) AND\n".
      "    page_namespace={$NScat}";

   // $wgOut->addHTML("<pre>$sql</pre>");
  return $sql;
}
  

  /**
   * @brief Embeds category graph into page.
   *
   * @param title page title
   */
static  function doQuery($title = null) {
    global $wgOut;

$xyCategoryGraphStyle = array(
   "COLOR_NODE"          => "#00EEEE", // color of category nodes
   "COLOR_NODE_ERROR"    => "#FF0000", // color for internal error
   "COLOR_NODE_REDIRECT" => "#FFCCCC", // color of redirected category nodes
   "COLOR_NODE_MISSING"  => "#FF0000", // color of missing category nodes
   "COLOR_LINK_REDIRECT" => "#FF0000", // color of redirect links
   "HEIGHT"              => "1920",    // height in pixels (96th of an inch)
   "WIDTH"               => "768"      // width in pixels (96th of an inch)
 );

  $colorNode         = $xyCategoryGraphStyle["COLOR_NODE"];
  $colorNodeError    = $xyCategoryGraphStyle["COLOR_NODE_ERROR"];
  $colorNodeRedirect = $xyCategoryGraphStyle["COLOR_NODE_REDIRECT"];
  $colorNodeMissing  = $xyCategoryGraphStyle["COLOR_NODE_MISSING"];
  $colorLinkRedirect = $xyCategoryGraphStyle["COLOR_LINK_REDIRECT"];
  $height            = $xyCategoryGraphStyle["HEIGHT"]/96;
  $width             = $xyCategoryGraphStyle["WIDTH"]/96;
 
  $redirections= Array();
  $nodes=Array();
  
  # Start digraph and set defaults
  $dot = "digraph a {\nsize=\"{$width},{$height}\";\nrankdir=LR;\n".
         "node [height=0 style=\"filled\", shape=\"box\", ".
         "font=\"Helvetica-Bold\", fontsize=\"10\", color=\"#00000\"];\n";

  $dbr = wfGetDB( DB_REPLICA );
  $sql= self::getSQLCategories($title);
  $res = $dbr->query($sql, __METHOD__);          // Run the query; returns IResultWrapper or false     // TODO: missing error handling in case of false

  danteLog ("DanteCategoryBrowser", "first query: number of rows: " . $res->numRows ($res) . "\n");
  for ( $i = 0; $obj = $res->fetchRow($res); $i++ ) {
    danteLog ("DanteCategoryBrowser", "first query, object $i: " . print_r ($obj, true) . "\n");

    $l_title = Title::makeTitle(NS_CATEGORY, $obj["cat"]);
 
    $color = $colorNode;
    if($obj["redirect"]==1)  $color = $colorNodeRedirect;
    if($obj["missing"]== 1)  $color = $colorNodeMissing;

    $nodes[$obj["cat"]] = array(
      'color' => $color,
      'url'   => $l_title->getFullURL(),
      'peri'  => 1,
      'label' => str_replace( '_', ' ', $obj["cat"] )
    );

     danteLog ("DanteCategoryBrowser", "first query: node: " . print_r ($nodes[$obj["cat"]] , true)."\n");


    if ($title && $obj["cat"] == $title->getDBkey()) { $nodes[$obj["cat"]]['peri']=2; }

    if ($obj["redirect"]) {
      $article = WikiPage::factory($l_title);
      if ($article) {
        $text = $article->getContent();
        $rt = $text->getRedirectTarget();
        if ($rt) {
          if (NS_CATEGORY == $rt->getNamespace()) {
            $redirections[$l_title->getDBkey()] = $rt->getDBkey();
            if (!$nodes[$rt->getDBkey()]){
              $nodes[$rt->getDBkey()] = array(
                'color' => $colorNode,
                'url'   => $rt->getFullURL(),
                'peri'  => 1
                );
                }
              }
            }
          }
    }
  }  // end for loop over result set

  $sql= self::getSQLCategoryLinks($title);
  $res = $dbr->query($sql, __METHOD__);          // Run the query; returns IResultWrapper or false     // TODO: missing error handling in case of false

  danteLog ("DanteCategoryBrowser", "SECOND QUERY number of rows: " . $res->numRows ($res) . "\n");

  for ( $i = 0; $obj = $res->fetchRow( $res ); $i++ ) {
    danteLog ("DanteCategoryBrowser", "second query object: " . print_r ($obj, true) . "\n");

    $cat_from = Title::makeName(NS_CATEGORY, $obj["cat_from"]);
    $cat_to   = Title::makeName(NS_CATEGORY, $obj["cat_to"]);

    # If destination node has not been read highlight the error.  // TODO ?????
      if (@!$nodes[$obj["cat_to"]]){
        $rt = Title::makeTitle(NS_CATEGORY, $obj["cat_to"]);
        $nodes[$rt->getDBkey()] = array(
          'color' => $colorNodeError,
          'url'   => $rt->getFullURL(),
          'peri'  => ($title && $rt->getDBkey() == $title->getDBkey())? 2 : 1,
          'label' => str_replace( '_', ' ', $rt->getDBkey())
          );
        }
      else {
        danteLog ("DanteCategoryBrowser", "not highlioghted : " . print_r ($obj, true) . "\n");
      }

    if (!$redirections[$obj["cat_from"]] ||  $redirections[$obj["cat_from"]] != $obj["cat_to"]) {
      $dot .= "\"".$obj['cat_to']."\" -> \"".$obj['cat_from']."\" [dir=back];\n";
    }
    else {danteLog ("DanteCategoryBrowser", "not redirected : " . print_r ($obj, true) . "\n"); }
  }  // end for loop second query
  


  # Create redirection links
  foreach( $redirections as $cat_from => $cat_to) {
    $dot .= "\"$cat_to\" -> \"$cat_from\" [color=\"".$colorLinkRedirect."\", dir=back];\n";
  }

  foreach( $nodes as $l_DbKey=>$properties ) {   
    $l_title = Title::makeTitle(NS_CATEGORY, $l_DbKey);
    $dot .= "\"$l_DbKey\" [URL=\"{$properties['url']}\",".
        "peripheries={$properties['peri']},label=\"{$properties['label']}\",".
        "fillcolor=\"{$properties['color']}\"];\n";
  }
 
  $dot .= "}\n";
  //$wgOut->addHTML("<pre>${dot}</pre>"); // debug  this is the graphviz dot code
  return $dot;
}


static private function doDot( $title, $dot ) {  // Save dot file and generate png and map file
  global $wgOut, $danteDotPath;
  global $IP;
 
  $md5 = md5($title);
  $docRoot = "$IP/danteCatBrowserCache/";

  $fileDot = "$docRoot$md5.dot";
  $fileMap = "$docRoot$md5.map";
  $filePng = "$docRoot$md5.png";

  file_put_contents($fileDot, $dot);

  $cmd = "$danteDotPath -Tpng -o$filePng <$fileDot";

  // $wgOut->addHTML("$danteDotPath -Tpng -o$filePng <$fileDot");  // debug
  $result = shell_exec($cmd);

  danteLog ("DanteCategoryBrowser", "calling $cmd gave $result \n");

  // $wgOut->addHTML("$danteDotPath -Tcmap -o$fileMap <$fileDot");  // debug
  $map = shell_exec("$danteDotPath -Tcmap -o$fileMap <$fileDot");
}


public static function onRegistration () { 
  global $IP;
  if ( !file_exists ($IP."/danteCatBrowserCache") ) {$retVal = mkdir ($IP."/danteCatBrowserCache", 0755);}
}


static function showImg( $title ) {  // output the image; title to generate md5 for filename
  global $wgOut, $wgScriptPath, $wgServer;
  global $IP;
  $docRoot = "$IP/danteCatBrowserCache/";
  $md5 = md5($title);
  $fileMap = "$docRoot$md5.map";
  if (true || file_exists($fileMap)) {
    $map = file_get_contents($fileMap);

    $mapStr = htmlspecialchars ($map);
    //$wgOut->addHTML("<pre>$mapStr</pre>");  // debug
 
    $URLpng =  $wgServer.$wgScriptPath."/danteCatBrowserCache/$md5.png";
   //$wgOut->addHTML("<pre>URL: $URLpng</pre>");  // debug


    $wgOut->addHTML("<DIV style='border:1px solid green;' id='xyCategoryBrowser'><IMG src=\"$URLpng\" usemap=\"#map1\" alt=\"$title\"><MAP name=\"map1\">$map</MAP></DIV>");
    return true;
    }
}

}
?>
