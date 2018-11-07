<?php
	/*
	 * DISCLAIMER
	 * 	hidden files not considered
	 * 	file names assumed to end with proper extension ('.png', '.css', etc.)
 	*/

	/*
	 * Script entry point
	 * path to directory containing all PNGs always expected as last argument
	 * creates nd uses helper class 'CSS_Generator'
	*/

	$target = $argv[$argc-1];
	if (!is_dir($target))
	{
		echo "CSS Generator expects a valid folder path" . PHP_EOL;
		exit;
	}
	else
	{
		new CSS_Generator($target, $argv);
	}

	class CSS_Generator
	{
		private $opts;
		private $images;
		private $cssData = [];
		private $masterImg;

		/*
		 * Cunstructor serves as request executor
		*/
		public function __construct(string $target, array $args)
		{
			$this->set_opts($args);
			$this->images = glob("$target/*.png");
			if ($this->opts["r"]) {
				$this->rec_glob_pngs($target);
			}
			if (count($this->images) > 0)
			{
				$this->setMasterImg();
				$this->append_imgs();
				imagepng($this->masterImg, $this->opts["i"]); //NOTICE compression and file size options
				$this->gen_css();
			}
		}

		/*
		 * reads specified options from passed arguments and sets to appropriate fields, with default values
		*/
		private function set_opts(array $args)
		{
			$args = array_slice($args, array_search(__FILE__, $args));
			array_pop($args);
			$opts = ["r" => FALSE, "i" => "sprite.png", "s" => "style.css"];
			foreach ($args as $arg) {
				switch ($arg)
				{
					case "-r":
					case "-recursive":
						$opts["r"] = TRUE;
						break;
					case "-i":
					case "-output-image":
						$val = $this->getOptVal($arg, $args);
						$this->checkPath($val);
						$opts["i"] = $val;
						break;
					case "-s":
					case "-output-style":
					$val = $this->getOptVal($arg, $args);
					$this->checkPath($val);
						$opts["s"] = $val;
						break;
				}
			}
			$this->opts =  $opts;
		}

		/*
		 * Options with values expect them at next index of arguments
		 * Not to be used for options with multiple or optional values
		*/
		private function getOptVal(string $arg, array $args)
		{
			$argI = array_search($arg, $args);
			if (count($args) <= $argI) {
				echo __FILE__ . " : option requires an argument -- 'l'" . PHP_EOL;
				exit();
			}
			else {
				return $args[$argI + 1];
			}
		}

		private function checkPath(string $path)
		{
			if (file_exists($path) || !is_writable(dirname($path))) {
				echo __FILE__ . " : cannot write to '$path' : file exists or permissions missing" . PHP_EOL;
				exit;
			}
		}

		/*
		 * recurse subdirectories for PNGs
		 */
		private function rec_glob_pngs(string $folder)
		{
			$dirs = glob("$folder/*[^.]", GLOB_ONLYDIR);
			foreach ($dirs as $dir)
			{
				$this->images = array_merge($this->images, glob("$dir/*.png"));
				$this->rec_glob_pngs("$dir");
			}
		}

		/*
		 * calculate expected dimensions and create master image
		 */
		private function setMasterImg ()
		{
			$w = 0;
			$h = 0;
			foreach ($this->images as $img)
			{
				$img = imagecreatefrompng($img);
				$w += imagesx($img);
				$imgY = imagesy($img);
				if ($imgY > $h)
				{
					$h = $imgY;
				}
			}
			$this->masterImg = imagecreate($w,$h);
		}

		/*
		 * copies all images to $masterImg and sets needed data for CSS
		 */
		private function append_imgs()
		{
			$destX = 0;
			foreach ($this->images as $imageName)
			{
				$image = imagecreatefrompng($imageName);
				$imgW = imagesx($image);
				$imgH = imagesy($image);
				imagecopy($this->masterImg, $image, $destX, 0, 0, 0, $imgW, $imgH);
				$destX += $imgW;
				$this->cssData[pathinfo($imageName, PATHINFO_FILENAME)] = ['width' => $imgW, 'height' => $imgH];
			}
		}

		/*
		 * writes data from append_imgs() via $cssData to CSS file according to format, with a stamp
		 */
		private function gen_css()
		{
			$output = '/* Generated by ' . __FILE__ . ' at ' . date("d-m-Y h:i a e") . ' */' . PHP_EOL . PHP_EOL;
			$output .= '.img {' . PHP_EOL .
				"\tdisplay: inline-block;" . PHP_EOL .
				"\tbackground: url('" . basename($this->opts['i']) . "') no-repeat;" . PHP_EOL .
				'}' . PHP_EOL . PHP_EOL;

			$lastX = 0;
			$format = ".img.img-%s {" . PHP_EOL .
				"\tbackground-position: -%dpx -0px;" . PHP_EOL .
				"\twidth: %dpx;" . PHP_EOL .
				"\theight: %dpx;" . PHP_EOL .
				'}' . PHP_EOL . PHP_EOL;
			foreach ($this->cssData as $spriteName => $dimens) {
				$output .= sprintf($format, $spriteName, $lastX, $dimens['width'], $dimens['height']);
				$lastX += $dimens['width'];
			}
			file_put_contents($this->opts['s'],$output);
		}
	}