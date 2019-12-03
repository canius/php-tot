<?php
	
	/*	
	Created by Canius Chu
	Nov 22 2019
	 */

	namespace PHP_TOT_OTAServer;

	require_once  'Classes/TOTClasses/XMLHelper.php';
	require_once  'Classes/TOTClasses/SortHelper.php';
	require_once  'Classes/TOTClasses/FileSystemHelper.php';

	function allVersionInfoForIdentifier($identifier)
	{
		$dir = "Documents/" . $identifier . "/";
		$maxBetaVersion = 0;
		$maxBetaVersionInfoArray = [];
		if (file_exists($dir))
		{
			if ($handle = opendir($dir))
			{
				while (false !== ($file = readdir($handle)))
				{
					if ($file !== "." && $file !== ".." && is_dir($dir . $file))//如果$file是文件夹
					{
						$versionInfoPath = $dir . $file . "/VersionInfo.plist";
						if (file_exists($versionInfoPath))
						{
							$versionInfoArray = ArrayFromXMLPath($versionInfoPath);
							$versionInfoArray["Identifier"] = $identifier;	
							$maxBetaVersionInfoArray[] = $versionInfoArray;
						}
					}
				}
				closedir($handle);
			}
		}
		return $maxBetaVersionInfoArray;
	}

	function allApps()
	{
		$documentPath = "Documents/";
		$appsInfoPath = $documentPath . "apps.plist";
		$appInfos = array();
		if (file_exists($appsInfoPath))
		{
			$apps = ArrayFromXMLPath($appsInfoPath);
			foreach ( $apps as $item ) 
			{
				$identifier = $item["Identifier"];
				if ($identifier !== "." && $identifier !== ".." && is_dir($documentPath . $identifier))//如果$file是文件夹
				{
					$appInfos[] = allVersionInfoForIdentifier($identifier);
				}
			}
		}
		return $appInfos;
	}

	function cleanup($max)
	{
		$sortHelper = new SortHelper;
		foreach (allApps() as $versions)
		{
			$sorted = $sortHelper->ReversedSortArrayWithKey($versions, 'ReleaseDate');
			$index = 0;
			foreach ($sorted as $version)
			{
				if ($index >= $max) 
				{
					$identifier = $version["Identifier"];
					$dir = "Documents/" . $identifier . "/" . $version["BetaVersion"];
					DeleteDir($dir);
				}
				$index++;
			}
		}
	}
?>