<?
include('font.php');
$data=file_get_contents('DroidSansFallback.ttf');
$font=new Font($data);
if($argv[1]=="latin1"){
    $getLatin1=true;
    $str=$argv[2];
}else{
    $getLatin1=false;
    $str=$argv[1];
}
$fontData=$font->getSubset($str,$getLatin1);
$dir='tmp';
$filename=$dir."/".md5($fontData);
file_put_contents($filename.".ttf",$fontData);

exec("./fixfont.pe $filename.ttf $filename-fix.ttf");
exec("mv $filename-fix.ttf $filename.ttf");
exec("ttf2eot $filename.ttf > $filename.eot");
//echo $fontData;

