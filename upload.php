<?php
	
	/*	
	Created by Open Fibers
	25'O Clock Inc.
	Sep 9 2012
	 */

	namespace PHP_TOT_OTAServer;

	error_reporting( E_ALL );
	ini_set( 'display_errors', 'on' );

	require_once 'Classes/TOTClasses/unzip.php';
	require_once 'Classes/TOTClasses/HandleInfoPlistInPayload.php';
	require_once 'Classes/TOTClasses/FileSystemHelper.php';
	require_once 'Classes/TOTClasses/GenManifest.php';
	require_once 'Classes/TOTClasses/XMLHelper.php';

	require_once 'Classes/ApkParser/ApkParser.php';

	require_once 'cleanup.php';

/*
* $tempFile : handle of uploaded temp file
* return value : is temp file available
*/
	function isTempFileAvailable($tempFile)
	{
		$isFileAvailabel = false;
		echo "<pre>";
		if (($_FILES["file"]["type"] != "application/octet-stream"))//file is not an ipa
		{
			echo "File is not an ipa\n";
		}
		else if ($_FILES["file"]["size"] > 1024 * 1024 * 800)//ipa file size limit to 800M
		{
			echo "File should not be larger than 800M\n";
		}
		else if ($_FILES["file"]["error"] > 0)//上传失败
		{
    		echo "Return Code: " . $_FILES["file"]["error"] . "\n";
		}
		else//Upload successed
		{
			echo "File Available";
			$isFileAvailabel = true;
		}
		echo "</pre>";
		return $isFileAvailabel;
	}

//Move uploaded ipa file from temp dir to "./Temp/"
/*
* $tempFile : uploaded ipa file
* return : new fileName of ipa
*/
	function moveTempFileToTempDir($tempFile)
	{
		print("<pre>");

		$uploadDir = 'Temp/';
		CreateDir($uploadDir);

		$filename = "BetaTest.ipa";
		if( strtolower( substr( $tempFile["name"], -4 ) ) == ".apk" )
			$filename = "BetaTest.apk";

		//将其复制到非临时目录
		$tempPath = $tempFile['tmp_name'];
		$uploadPath = $uploadDir . $filename;

		echo "Moving From " . $tempPath . " to " . $uploadPath . "<br />";
		$isMoveSuccessed = false;
		if (move_uploaded_file($tempPath, $uploadPath))
		{
			print "Upload Successed\n";
			$isMoveSuccessed = true;
		}
		else
		{
			print "Move temp file failed, may be a permission issue\n";
		}
		print("</pre>");
		if ($isMoveSuccessed)
		{
			return $filename;
		}
		return null;
	}

/*
* $tempFile : handle of uploaded temp file
* return value : path of unziped dir of ipa if unzip successed. else null
*/
	function unzipIpaFile($ipaFile)
	{
		unzip($ipaFile, "Temp/");
		return "Temp/";
	}

/*
* $unzipPath : unzipPath of ipa
* return value : path of app dir in unzipPath
*/
	function appPathFromUnzipPath($unzipPath)
	{
		print("<pre>");
		$payloadPath = $unzipPath . "Payload";
		$appPath = null;
		if (file_exists($payloadPath))
		{
			if ($handle = opendir($payloadPath))
			{
				while (false !== ($file = readdir($handle)))
				{
					$isContain = strstr($file, ".app");
					if ($isContain)
					{
						$appPath = $payloadPath . "/" . $file . "/";
					}
				}
				closedir($handle);
			}
		}
		print("</pre>");
		return $appPath;
	}

//如果identifer对应的文件夹在Documents中不存在
//或文件夹中不含AppInfo.plist
//或AppInfo.plst中数据不正确
//则删除重建
	function CreateDirForIdentifier($identifier)
	{
		$pathForIdentifier = "Documents/" . $identifier . "/";
		if (!file_exists($pathForIdentifier))
		{
			CreateDir($pathForIdentifier);
			$xmlArray = array('LastVersion' => "0");
			SaveArrayAsXMLToPath($xmlArray, $pathForIdentifier . "AppInfo.plist");
		}
		else if (file_exists($pathForIdentifier) && !file_exists($pathForIdentifier . "AppInfo.plist"))
		{
			DeleteDir($pathForIdentifier);
			CreateDir($pathForIdentifier);
			$xmlArray = array('LastVersion' => "0");
			SaveArrayAsXMLToPath($xmlArray, $pathForIdentifier . "AppInfo.plist");
		}
		else
		{
			$xmlArray = ArrayFromXMLPath($pathForIdentifier . "AppInfo.plist");
			if (!$xmlArray["LastVersion"])
			{
				DeleteDir($pathForIdentifier);
				CreateDir($pathForIdentifier);
				$xmlArray = array('LastVersion' => "0");
				SaveArrayAsXMLToPath($xmlArray, $pathForIdentifier . "AppInfo.plist");
			}
		}
	}

	function moveTempIpaToIdentifierDir(
		$identifier, /*identifier of app */
		$ipaPath, /*path of uploaded ipa file */
		$ipaFileName, /*file name of uploaded ipa file */
		$appPath, /*unziped dir of "*.app/"*/
		$infoArray, /*Info array of app */
		$changeLog /*Change Log of this release */
		)
	{
		//在"Documents/$bundleIdentifier"下新建文件夹$dir,文件夹名为内测版本号
		$identifierXMLPath = "Documents/". $identifier . "/AppInfo.plist";
		$identifierInfoArray = ArrayFromXMLPath($identifierXMLPath);
		$betaVersion = $identifierInfoArray['LastVersion'];
		$betaVersion++;
		$identifierInfoArray['LastVersion'] = $betaVersion;
		SaveArrayAsXMLToPath($identifierInfoArray, $identifierXMLPath);
		$dir = "Documents/" . $identifier . "/" . $betaVersion . "/";
		CreateDir($dir);

		//将ipaPath的文件移动进$dir
		MoveFile($ipaPath, $dir . $ipaFileName);

		$appIconArray = array("AppIcon60x60@2x.png","AppIcon76x76@2x~ipad.png");
		foreach ($appIconArray as $key => $value) {
			if (file_exists($appPath . $value))
			{
				MoveFile($appPath . $value, $dir . "iTunesArtwork.png");
				break;
			}
		}

		date_default_timezone_set('PRC'); //中华人民共和国时间
		$now = strtotime("0 day");
		echo date('F d, Y H:i:s', $now);


		//在$dir中创建VersionInfo.plist
		//储存如下信息
		//Title -- $infoArray["BundleDisplayName"]
		//Bundle Identifier -- $infoArray["BundleIdentifier"]
		//Version -- $infoArray["Version"]
		//BetaVersion -- $betaVersion
		//ReleaseDate -- $now
		//ChangeLog -- $changeLog
		$versionInfoArray = array(
			'Title' => $infoArray["BundleDisplayName"],
			'BundleIdentifier' => $infoArray["BundleIdentifier"],
			'Version' => $infoArray["Version"],
			'BetaVersion' => $betaVersion,
			'ReleaseDate' => $now,
			'ChangeLog' => $changeLog
		);
		SaveArrayAsXMLToPath($versionInfoArray, $dir . "VersionInfo.plist");

		// function GenManifestXMLString(
		// $ipaURL,
		// $bundleIdentifier,
		// $bundleVersion,
		// $title)
	}

	function ArrayFromApkPath( $apkPath )
	{
		$returnArray = null;
		if (file_exists($apkPath))
		{
			$apk = new \ApkParser( $apkPath );
			$manifest = $apk->getManifest();

			$versionString     = $manifest->getVersionName();
			$bundleIdentifier  = $manifest->getPackageName();
			$minOSVersion      = $manifest->getMinSdkLevel();

			$ids = explode( ".", $bundleIdentifier );
			$idsCount = count( $ids );
			if( $idsCount >=4 )
				$bundleDisplayName = ucfirst( $ids[$idsCount - 2] ) . ' ' . ucfirst( $ids[$idsCount - 1] );
			else
				$bundleDisplayName = ucfirst( $ids[$idsCount - 1] );

			// print("<pre>");
			// var_dump($plistArray);
			// print("</pre>");

			$returnArray = array();

			$returnArray['Version'] = $versionString;
			$returnArray['BundleIdentifier'] = $bundleIdentifier;
			$returnArray['BundleDisplayName'] = $bundleDisplayName;
			$returnArray['MinOSVersion'] = $minOSVersion;

		}
		return $returnArray;
	}

	function main()
	{
		$identifier = $_POST['app'];

		if (!$_FILES)//$_FILES中没有值，上传失败
		{
			echo "Upload failed <br/>";
			return;
		}

		//change log为空或为"input change log here"，上传失败
		$changeLog = $_POST["changelog"];
		if (!$changeLog || $changeLog === "input change log here")
		{
			echo "Change log is required.<br/>";
			return;
		}

		//如果文件不符合要求，上传失败
		$isFileAvailabel = isTempFileAvailable($_FILES["file"]);
		if (!$isFileAvailabel)
		{
			return;
		}

		//上传成功，开始干些正事
		//先新建Documents用来存放ipa，版本信息等
		//再建个Temp文件夹用于暂时保存上传的ipa和解压ipa
		CreateDir("Documents");
		CreateDir("Temp");

		$appsInfoPath = "Documents/apps.plist";
		$identifiers = array();
		if (file_exists($appsInfoPath))
		{
			$apps = ArrayFromXMLPath($appsInfoPath);
			$identifiers = array_column($apps, 'Identifier');
		}

		if (!in_array($identifier, $identifiers)) {
    		echo "error: app not exits.";
    		return;
		}

		//将临时ipa文件从php临时文件夹移动到相对路径下的Temp文件夹
		$ipaFileName = moveTempFileToTempDir($_FILES["file"]);
		$ipaPath = "Temp/" . $ipaFileName;

		if( strtolower( substr( $_FILES["file"]["name"], -4 ) ) == ".apk" )
		{
			$appPath = $ipaPath;

			$infoArray = ArrayFromApkPath( $ipaPath );
		}
		else
		{
			//解压上传的ipa文件
			$unzipPath = unzipIpaFile($ipaPath);
			$appPath = appPathFromUnzipPath($unzipPath);

			//从解压出的文件中找到Info.plist,解析并找到我们感兴趣的信息放入$infoArray
			$infoArray = ArrayFromInfoPlistPath($appPath . "Info.plist");
		}

		//如果Info.plist解析失败，删掉Temp文件夹返回吧
		if (!$infoArray)
		{
			echo $appPath . "Info.plist" . " parse failed";
			DeleteDir("Temp");
			return;
		}

		//以bundle identifier为名称在Documents下建个文件夹
		CreateDirForIdentifier($identifier);

		//将ipa移动进刚刚创建的文件夹。
		//此方法这么多参数当然不会只移动ipa了，同时移动的还有iTunesArtwork
		//顺便将此次提交的ipa的我们感兴趣的信息存成XML放到刚创建的文件夹
		moveTempIpaToIdentifierDir(
			$identifier, //bundle identifier
			$ipaPath, //Temp文件夹中的ipa的路径
			$ipaFileName, //上传的文件名
			$appPath, //解压好的"xxx.app/"的路径，将从此路径读取iTunesArtwork
			$infoArray, //从Info.plist中解析好的数据
			$changeLog //此次提交的change log
		);

		//Remove temp file
		DeleteDir("Temp");
		cleanup($identifier, 20);
	}
	main();
?>