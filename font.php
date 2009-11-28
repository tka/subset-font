<?
class Font{
	private $_offset=0;
	private $data;
	private $tables=array();

	function __construct(&$data){
		$this->data=$data;
		$this->sfntVersion=$this->_get(4);
		$this->numTables=$this->_get(2);
		$this->searchRange=$this->_get(2);
		$this->entrySelector=$this->_get(2);
		$this->formatInt=$this->_get(2);
		$numTables=PackData::toUShort($this->numTables);
		for($i=0;$i<$numTables;$i++ ){
			$tag=$this->_get(4);
			switch(strtolower($tag)){
			case 'os/2':
				$tableClass='os2';
				break;
			case ':cff':
				$tableClass='cff';
				break;
			case 'cvt ':
				$tableClass='cvt';
				break;
			default:
				$tableClass=strtolower($tag);
			}
			$objName=$tableClass.'Table';
			$this->tables[]=$objName;
			$this->$objName=new $tableClass();
			$this->$objName->tag=$tag;
			$this->$objName->checkSum=$this->_get(4);
			$this->$objName->offset=$this->_get(4);
			$this->$objName->length=$this->_get(4);
			$this->$objName->setData(substr($data,PackData::toULong($this->$objName->offset),PackData::toULong($this->$objName->length)));
		}
		$this->_offset=0;
	}
	public function getGlyphId($char){
		return $this->cmapTable->getGlyphId($char);
	}

	public function getGlyph($glyphId){
		$offset=$this->locaTable->getOffset($glyphId);
		$length=$this->locaTable->getLength($glyphId);
		if($length==0){
			return '';
		}else{
			return substr($this->glyfTable->data,$offset,$length);
		}
	}

	public function getSubset($string,$getLatin1=false){
		$glyphIds=array() ; // charCode => glyphId
		$glyphs=array();	// glyphId => glyph
        $fontNumber=mb_strlen($string,'utf8');
        if($getLatin1){
            $ords=range(1,255);
        }else{
            $ords=array();
        }
		for($i=0;$i<$fontNumber;$i++){
			$ord=uniord(mb_substr($string,$i,1,'UTF-8'));
			$ords[]=$ord;
        }

		$ords=array_unique($ords);
		sort($ords,SORT_NUMERIC);
		//error_log(print_r($ords,1));
		$glyphs[0]=$this->getGlyph(0);
		//取得指定字元的的glyph
		foreach($ords as $index=>$ord){
			$glyphId=$this->getGlyphId($ord);
			if($glyphId!==0){
				$glyphIds[$ord]=$glyphId;
				if(!isset($glyphs[$glyphId])){
					$glyphs[$glyphId]=$this->getGlyph($glyphId);
				}
			}
		}
		foreach($glyphs as $glyphId=>$glyph){
			$this->getComponentsByGlphy($glyph,$glyphs);
		}
		//print_r($glyphIds);
		//print_r(array_keys($glyphs));
		$tables=array();
		$tables['cmap']=$this->cmapTable->getNewDataWithGlyphIds($glyphIds);
		$tables['head']=$this->headTable->data;
		$tables['hhea']=$this->hheaTable->getNewDataByGlyphs($glyphs);
		$tables['hmtx']=$this->hmtxTable->getNewDataByNumOfHMetricsAndGlyphs($this->hheaTable->getNumOfHMetrics(),$glyphs);
		$tables['maxp']=$this->maxpTable->getNewDataByGlyphs($glyphs);
		$tables['name']=$this->nameTable->data;
		$tables['OS/2']=$this->os2Table->data;
		$tables['post']=$this->postTable->data;
		$tables['cvt ']=$this->cvtTable->data;
		$tables['fpgm']=$this->postTable->data;
		$tables['glyf']=$this->glyfTable->getNewDataByGlyphs($glyphs);
		$tables['loca']=$this->locaTable->getNewDataByGlyphs($glyphs);
		$tables['prep']=$this->postTable->data;

		$tables['gasp']=$this->gaspTable->data;
		$tables['kern']=pack('nnnnn',0,1,0,6,1);//暫不處理kern table
		//$tables['kern']=$this->kernTable->data;

		$font=substr($this->data,0,12);
		$offset=12+16*count($tables);
		foreach($tables as $tag=>$data){
			$dataLength=strlen($data);
			$font.= $tag.pack('NNN',0,$offset,$dataLength);
			$offset+=$dataLength;
		}
		$font.=join('',$tables);
		return $font;
	}
	private function getComponentsByGlphy($glyph,&$glyphs){
		if(glyf::isComposite($glyph))
			foreach($this->glyfTable->getComponentIdsByGlphy($glyph) as $componentGlyphId){
				if(!isset($glyphs[$componentGlyphId])){
					$glyphs[$componentGlyphId]=$this->getGlyph($componentGlyphId);
					$this->getComponentsByGlphy($glyphs[$componentGlyphId],$glyphs);
				}
			}
	}
	private function _get($len){
		$return_data=substr($this->data,$this->_offset,$len);
		$this->_offset=$this->_offset+$len;
		return $return_data;
	}


}
class PackData{
	static function toUShort(&$value){
		try{
			$array=(unpack('n',$value));
		}catch(Exception $e){
			print_r(debug_backtrace());
		}
		return array_pop($array);
	}
	static function toShort(&$value){
		$array=(unpack('s',$value));
		return array_pop($array);
	}
	static function toULong(&$value){
		$array=(unpack('N',$value));
		return array_pop($array);
	}
}
class FontTable{
	public $tag;
	public $checkSum;
	public $offset;
	public $length;
	public $data;
	function dumpdata(){
		$a=$this->data;
		$a=bin2hex($a);
		$a_len=strlen($a);
		for($i=0;$i<$a_len;$i++){
			if($i%16==0) echo sprintf("%5d: ",$i/2);
			echo $a[$i];
			if($i%2==1)echo " ";
			if($i%8==7)echo "| ";
			if($i%16==15)echo "\n";
			if($i==$a_len-1)echo "\n";

		}
	}
	function setData($data){
		$this->data=$data;
		//file_put_contents('bin_data/'.get_called_class(),$this->data);
	}
}
class cmap extends FontTable{
	public function setData($data){
		parent::setData($data);
		$this->version=substr($data,0,2);
		$this->numTables=substr($data,2,2);
		$this->tables=array();
		for($i=0;$i<PackData::toUShort($this->numTables);$i++){
			$this->tables[$i]['platformID']=substr($data,4+$i*8,2);
			$this->tablse[$i]['encodingID']=substr($data,4+$i*8+2,2);
			$this->tables[$i]['offset']=substr($data,4+$i*8+4,4);
			$offset=PackData::toULong($this->tables[$i]['offset']);
			$this->tables[$i]['format']=substr($data,$offset,2);
			$this->tables[$i]['reserved']=substr($data,$offset+2,2);
			$this->tables[$i]['length']=substr($data,$offset+4,4);
			$this->tables[$i]['language']=substr($data,$offset+8,4);
			$this->tables[$i]['nGroups']=substr($data,$offset+12,4);
		}

	}
	public function getGlyphId($char){
		if(intval($char)){
			$charCode=$char;
		}else{
			$charCode=uniord($char);
		}
		//only support 1 table and format=12
		switch(PackData::toUShort($this->tables[0]['format'])){
		case 12:
			//取得index 區段資料內容
			$data=substr($this->data,PackData::toULong($this->tables[0]['offset'])+16,PackData::toULong($this->tables[0]['length'])-16);
			$groupNum=PackData::toULong($this->tables[0]['nGroups']);
			for($i=0;$i<$groupNum;$i++){
				$endCharCode=PackData::toULong(substr($data,$i*12+4,4));
				if($endCharCode>=$charCode){
					$startCharCode=PackData::toULong(substr($data,$i*12,4));
					if($startCharCode<=$charCode){
						$startGlyphId=PackData::toULong(substr($data,$i*12+8,4));
						return $charCode-$startCharCode+$startGlyphId;
					}
				}
			}
		default:
			return 0;
		}
	}
	public function getNewDataWithGlyphIds(&$glyphIds){
		//只支援單table,format=12格式
		$bin='';
		$bin.= $this->version;
		$bin.= $this->numTables;
		for($i=0;$i<PackData::toUShort($this->numTables);$i++){
			$bin.= $this->tables[$i]['platformID'];
			$bin.= $this->tablse[$i]['encodingID'];
			$bin.= $this->tables[$i]['offset'];

			$bin.= $this->tables[$i]['format'];
			$bin.= $this->tables[$i]['reserved'];
			$bin.= pack('N',12*count($glyphIds)+16); //$this->tables[$i]['length']
			$bin.= $this->tables[$i]['language'];
			$bin.= pack('N',count($glyphIds)); //$this->tables[$i]['nGroups']
		}
		$newGlyphId=1;
		foreach($glyphIds as $charCode=>$glyphId){
			$bin.=pack('NNN',$charCode,$charCode,$newGlyphId);
			$newGlyphId++;
		}
		return $bin;
	}

}
class head extends FontTable{
}
class hhea extends FontTable{
	public function getNumOfHMetrics(){
		return PackData::toUShort(substr($this->data,-2));
	}
	public function getNewDataByGlyphs(&$glyphs){ 
		return substr($this->data,0,-2).pack('n',count($glyphs)); //hmtx 全部獨立計算
	}
}
class hmtx extends FontTable{
	public function getNewDataByNumOfHMetricsAndGlyphs($numOfHMetrics,&$glyphs){
		$lastOffset=4*$numOfHMetrics-4;
		$lastAdvanceWidth=substr($this->data,$lastOffset,2);
		$bin='';
		foreach(array_keys($glyphs) as $index=>$glyphId){
			if($glyphId<$numOfHMetrics){
				$bin.=substr($this->data,4*$glyphId,4);
			}else{
				$bin.=$lastAdvanceWidth.substr($this->data,$lastOffset+2*($glyphId-$numOfHMetrics),2);
			}
		};
		return $bin;
	}
}
class maxp extends FontTable{
	public function getNewDataByGlyphs(&$glyphs){
		if(PackData::toULong($this->data)==0x00010000){
			return pack('Nn',0x00010000,count($glyphs)).substr($this->data,6);
		}else{
			return pack('Nn',0x00005000,count($glyphs)).substr($this->data);
		}
	}
}
class name extends FontTable{
}
class os2 extends FontTable{
}
class post extends FontTable{
}
class cvt extends FontTable{
}
class fpgm extends FontTable{
}
class glyf extends FontTable{
	public static function isComposite(&$glyph){
		if(empty($glyph)){
			return false;
		}
		if(PackData::toShort($glyph)==-1){
			return true;
		}
		return false;
	}
	public function getComponentIdsByGlphy($data){
		$components=array();
		$strlen=strlen($data);
		$offset=10;//format default
		while($offset<$strlen){
			$glyphId=PackData::toUShort(substr($data,$offset+2,2));
			$componentIds[]=$glyphId;
			if(substr($data,$offset,2) & 0x0001){
				$offset=$offset+8;//arg is word
			}else{
				$offset=$offset+6;// arg is byte
			}
		}
		return $componentIds;
	}
	public function getNewDataByGlyphs(&$glyphs){
		$oGlyphIds=array_keys($glyphs);
		$bin='';
		foreach($glyphs as $glyphId=>$glyph){
			if(self::isComposite($glyph)){
				$bin.= substr($glyph,0,10);
				for($offset=10;$offset<strlen($glyph);){
					$flag=substr($glyph,$offset,2);
					$componentId=PackData::toUShort(substr($glyph,$offset+2,2));
					if($flag & 0x0001){
						$bin.= $flag.pack('n',indexOf($componentId,$oGlyphIds)).substr($glyph,$offset+4,4);
						$offset=$offset+8;//arg is word
					}else{
						$bin.= $flag.pack('n',indexOf($componentId,$oGlyphIds)).substr($glyph,$offset+4,2);
						$offset=$offset+6;//arg is byte
					}
				}
			}else{
				$bin.=$glyph;
			}
		}
		return $bin;
	}
}
class loca extends FontTable{
	function getOffset($glyphId){
		return PackData::toULong(substr($this->data,$glyphId*4,4));
	}
	function getLength($glyphId){
		return PackData::toULong(substr($this->data,$glyphId*4+4,4))-PackData::toULong(substr($this->data,$glyphId*4,4));
	}
	function getNewDataByGlyphs(&$glyphs){
		$bin=pack('N',0);
		$offset=0;
		foreach($glyphs as $glyph){
			$offset+=strlen($glyph);
			$bin.=pack('N',$offset);
		}
		return $bin;
	}
}
class prep extends FontTable{
}
class cff extends FontTable{
}
class vorg extends FontTable{
}
class base extends FontTable{
}
class gdef extends FontTable{
}
class gpos extends FontTable{
}
class gsub extends FontTable{
}
class jstf extends FontTable{
}
class dsig extends FontTable{
}
class gasp extends FontTable{
}
class hdmx extends FontTable{
}
class kern extends FontTable{
	function getDataByGlyphIds($GlyphIds){
		$data=substr($this->data,0);
	}
}
class ltsh extends FontTable{
}
class pclt extends FontTable{
}
class vdmx extends FontTable{
}
class vhea extends FontTable{
}
class vmtx extends FontTable{
}
if(!function_exists('uniord')){
	function uniord($c) {

		$h = ord($c{0});
		if ($h <= 0x7F) {
			return $h;
		} else if ($h < 0xC2) {
			return false;
		} else if ($h <= 0xDF) {
			//return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
			//byte=2, 避免 0xC2A0 問題，故使用unpack
			return unpack("n", pack('n',0xc2a0));
		} else if ($h <= 0xEF) {
			return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
				| (ord($c{2}) & 0x3F);
		} else if ($h <= 0xF4) {
			return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
				| (ord($c{2}) & 0x3F) << 6
				| (ord($c{3}) & 0x3F);
		} else {
			return false;
		}
	}
}
function indexOf($needle, $haystack) {
	for($i = 0,$z = count($haystack); $i < $z; $i++){
		if ($haystack[$i] == $needle) {  //finds the needle
			return $i;
		}
	}
	return false;
}
