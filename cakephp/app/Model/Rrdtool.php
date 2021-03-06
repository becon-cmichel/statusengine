<?php
/**********************************************************************************
*
*    #####
*   #     # #####   ##   ##### #    #  ####  ###### #    #  ####  # #    # ######
*   #         #    #  #    #   #    # #      #      ##   # #    # # ##   # #
*    #####    #   #    #   #   #    #  ####  #####  # #  # #      # # #  # #####
*         #   #   ######   #   #    #      # #      #  # # #  ### # #  # # #
*   #     #   #   #    #   #   #    # #    # #      #   ## #    # # #   ## #
*    #####    #   #    #   #    ####   ####  ###### #    #  ####  # #    # ######
*
*                            the missing event broker
*
* --------------------------------------------------------------------------------
*
* Copyright (c) 2014 - present Daniel Ziegler <daniel@statusengine.org>
*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation in version 2
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*
* --------------------------------------------------------------------------------
*/

App::uses('Xml', 'Utility');

class Rrdtool extends AppModel{
	public $useTable = false;
	public $path = null;
	public $regEx = null;

	public function hasGraph($hostName, $serviceName){
		if($this->rrdExists($hostName, $serviceName)){
			if($this->xmlExists($hostName, $serviceName)){
				return true;
			}
		}
		return false;
	}

	public function isXmlParsable($hostName, $serviceName){
		try{
			$xmlFile = $this->path . $this->replace($hostName) . DS . $this->replace($serviceName).'.xml';
			$xml = Xml::build($xmlFile);
		}catch(Exception $e){
			return $e->getMessage();
		}
		return true;
	}

	public function rrdExists($hostName, $serviceName){
		if(!isset($this->path)){
			Configure::load('Perfdata');
			$this->path = Configure::read('perfdata.PERFDATA.dir');
		}
		$fileBase = $this->path . $this->replace($hostName) . DS . $this->replace($serviceName);
		if(file_exists($fileBase.'.rrd')){
			return true;
		}
		return false;
	}

	public function xmlExists($hostName, $serviceName){
		if(!isset($this->path)){
			Configure::load('Perfdata');
			$this->path = Configure::read('perfdata.PERFDATA.dir');
		}
		$fileBase = $this->path . $this->replace($hostName) . DS . $this->replace($serviceName);
		if(file_exists($fileBase.'.xml')){
			return true;
		}
		return false;
	}

	public function getPath($hostName, $serviceName){
		if(!isset($this->path)){
			Configure::load('Perfdata');
			$this->path = Configure::read('perfdata.PERFDATA.dir');
		}
		return $this->path . $this->replace($hostName) . DS . $this->replace($serviceName).'.rrd';
	}

	public function fetch($hostName, $serviceName){
		Configure::load('Perfdata');
		$options = [
			'AVERAGE',
			'--resolution',
			(int)Configure::read('perfdata.RRA.step'),
			'--start',
			(time() - 90 * 60),
			'--end',
			time(),
		];
		return rrd_fetch($this->getPath($hostName, $serviceName), $options);
	}

	public function parseXml($hostName, $serviceName){
		if(!isset($this->path)){
			Configure::load('Perfdata');
			$this->path = Configure::read('perfdata.PERFDATA.dir');
		}
		$xmlFile = $this->path . $this->replace($hostName) . DS . $this->replace($serviceName).'.xml';


		//Using CakePHP because of exceptions
		$xml = Xml::build($xmlFile);
		//$xml = simplexml_load_file($xmlFile);

		$return = [];
		$default = [
			'name' => null,
			'label' => null,
			'unit' => null,
			'ds' => 0
		];
		$xmlAsArray = Xml::toArray($xml);
		if(isset($xmlAsArray['NAGIOS']['DATASOURCE'])){
			if(isset($xmlAsArray['NAGIOS']['DATASOURCE'][0])){
				foreach($xmlAsArray['NAGIOS']['DATASOURCE'] as $datasource){
					if(!isset($datasource['UNIT'])){
						$datasource['UNIT'] = '';
					}
					$unit = $datasource['UNIT'];
					if($unit == '%%'){
						$unit = '%';
					}
					$return[$datasource['DS']] = Hash::merge($default, [
						'name' => $datasource['NAME'],
						'label' => $datasource['LABEL'],
						'unit' => $unit,
						'ds' => $datasource['DS']
					]);
				}
			}else{
				if(!isset($xmlAsArray['NAGIOS']['DATASOURCE']['unit'])){
					$xmlAsArray['NAGIOS']['DATASOURCE']['unit'] = '';
				}
				$unit = $xmlAsArray['NAGIOS']['DATASOURCE']['unit'];
				if($unit == '%%'){
					$unit = '%';
				}
				$return[$xmlAsArray['NAGIOS']['DATASOURCE']['DS']] = Hash::merge($default, [
					'name' => $xmlAsArray['NAGIOS']['DATASOURCE']['NAME'],
					'label' => $xmlAsArray['NAGIOS']['DATASOURCE']['LABEL'],
					'unit' => $unit,
					'ds' => $xmlAsArray['NAGIOS']['DATASOURCE']['DS']
				]);
			}
		}
		return $return;
	}

	public function replace($string){
		if(!isset($this->regEx)){
			Configure::load('Perfdata');
			$this->regEx = Configure::read('perfdata.replace_characters');
		}
		return preg_replace($this->regEx, '_', $string);
	}

	public function createErrorImage($error){
		$errorImage = imagecreatetruecolor(740, 250);
		imagesavealpha($errorImage, true);

		//Set Background
		$errorBg = imagecolorallocatealpha($errorImage, 231, 231, 231, 0);

		//Generate text color
		$textColor = imagecolorallocate($errorImage, 0, 0, 0);

		//Merge error image with background
		imagefill($errorImage, 0, 0, $errorBg);
		imagestring($errorImage, 5, 5, 5, 'Error while create graph image:', $textColor);

		$start = 30;
		if(is_string($error)){
			imagestring($errorImage, 5, 15, $start, $error, $textColor);
		}elseif(is_array($error)){
			$padding = 15;
			foreach($error as $errorLine){
				imagestring($errorImage, 5, 15, $start, $errorLine, $textColor);
				$start += $padding;
			}
		}else{
			imagestring($errorImage, 5, 5, $start, var_export($error, true), $textColor);
		}

		return $errorImage;
	}
}
