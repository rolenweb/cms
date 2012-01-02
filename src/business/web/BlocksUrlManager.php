<?php

class BlocksUrlManager extends CUrlManager
{
	private $_path = null;
	private $_pathSegments = null;
	private $_templateMatch = null;
	private $_requestExtension = null;
	private $_currentModule = null;

	public function init()
	{
		parent::init();

		// set this to false so extra query string parameters don't get the path treatment
		$this->appendParams = false;

		$this->_path = Blocks::app()->request->pathInfo;
		$this->_pathSegments = Blocks::app()->request->pathSegments;
		$this->_requestExtension = Blocks::app()->request->pathExtension;
	}

	public function processTemplateMatching()
	{
		// if it's a gii request, no need to do template matching.
		if (($this->getCurrentModule() !== null && $this->getCurrentModule()->getId() == 'gii') || strpos(Blocks::app()->request->getParam('r'), 'gii') !== false)
			return;

		$matchFound = false;

		// we'll never have a db entry match on a control panel request
		if (Blocks::app()->request->type == RequestType::Site)
		{
			if (Blocks::app()->isDbInstalled())
				if ($this->matchEntry())
					$matchFound = true;
		}

		if (!$matchFound)
			if (!$this->matchRoute())
				$this->matchTemplate();
	}

	public function getTemplateMatch()
	{
		return $this->_templateMatch;
	}

	public function getCurrentModule()
	{
		if ($this->_currentModule == null)
		{
			if ($this->_pathSegments !== null && isset($this->_pathSegments[0]))
			{
				if (($module = Blocks::app()->getModule($this->_pathSegments[0])) !== null)
					$this->_currentModule = $module;
			}
		}

		return $this->_currentModule;
	}


	/**
	 * Attempts to match a request with an entry in the database.  If one is found, we set the template match property.
	 * @return bool True if a match was found, false otherwise.
	 */
	public function matchEntry()
	{
		$pathMatchPattern = rtrim(Blocks::app()->request->serverName.Blocks::app()->request->scriptUrl.'/'.Blocks::app()->request->pathInfo, '/');

		$entry = Entries::model()->findByAttributes(array(
			'full_uri' => $pathMatchPattern,
		));

		if ($entry !== null)
		{
			$extension = pathinfo($entry->section->template, PATHINFO_EXTENSION);
			$this->setTemplateMatch($entry->section->template, $pathMatchPattern, $extension, TemplateMatchType::Entry);
			return true;
		}

		return false;
	}

	public function matchRoute()
	{
		/*
		if (Blocks::app()->request->type == RequestType::CP)
		{
			$routes = array(
				array('content/edit/{entryId}', 'content/_edit', array(
					'entryId' => RoutePatterns::Integer
				)),
				array('assets/edit/{path}', 'assets/_edit', array(
					'path' => RoutePatterns::Wild
				)),
				array('users/edit/{userId}', 'users/_edit', array(
					'userId' => RoutePatterns::Integer
				)),
			);
		}
		*/

		return false;
	}

	/**
	 * Attempts to match a request to a file on the file system.
	 * Will return false for any directory that has a "_" as the first character.
	 * Will attempt to match "path/to/folder/file.{allowedFileExtensions}" first, "path/to/folder/file/index.{allowedFileExtensions}" second.
	 * Sets the template match property if a match is found.
	 *
	 * @return bool True is a match is found, false otherwise.
	 */
	public function matchTemplate()
	{
		$moduleName = null;
		$templatePath = $this->normalizeTrailingSlash(Blocks::app()->getViewPath());
		$pathMatchPattern = rtrim(Blocks::app()->request->serverName.Blocks::app()->request->scriptUrl.'/'.Blocks::app()->request->pathInfo, '/');
		$tempPath = $this->_path;
		$testPath = null;

		// if the request comes in with an extension at the end, trim it off
		if ($this->_requestExtension !== null)
		{
			$pathMatchPattern = rtrim($pathMatchPattern, '.'.$this->_requestExtension);
			$tempPath = rtrim($tempPath, '.'.$this->_requestExtension);
		}

		// if this is a control panel request, let's see if we can match it to a module as well.
		if (Blocks::app()->request->type == RequestType::CP)
		{
			// we're dealing with a module
			if ($this->_currentModule !== null)
			{
				$moduleName = $this->_currentModule->getId();
				$requestPath = substr($tempPath, strlen($moduleName) + 1);

				if ($requestPath === false)
					$requestPath = '';
			}
			else
			{
				$requestPath = $tempPath;
			}
		}
		else
			$requestPath = $tempPath;

		// fix the trailing and ending slashes
		if ($requestPath !== '')
		{
			$requestPath = ltrim($requestPath, '\\/');
			$templatePath = $this->normalizeTrailingSlash($templatePath);
		}
		else
		{
			$templatePath = rtrim($templatePath, '\\/');
		}

		// if there are any folders that have a '_' as the first character of the name, then it's hidden and there is no template match.
		$requestPathSegs = explode('/', $requestPath);
		foreach ($requestPathSegs as $requestPathSeg)
		{
			if (isset($requestPathSeg[0]) && $requestPathSeg[0] == '_')
				return false;
		}

		// first try to match /path/to/folder.{allowedTemplateFileExtensions}
		if (($fullMatchPath = Blocks::app()->site->matchTemplatePathWithAllowedFileExtensions($templatePath.$requestPath)) !== null)
		{
			$extension = pathinfo($fullMatchPath, PATHINFO_EXTENSION);
			$this->setTemplateMatch($moduleName == null ? $requestPath : $moduleName.'/'.$requestPath, $pathMatchPattern, TemplateMatchType::Template, $extension);
			return true;
		}

		// now try to match /path/to/folder/index.{allowedTemplateFileExtensions}
		$requestPath = $this->normalizeTrailingSlash($requestPath).'index';
		$templatePath = rtrim($templatePath, '\\/').'/';
		if (($fullMatchPath = Blocks::app()->site->matchTemplatePathWithAllowedFileExtensions($templatePath.$requestPath)) !== null)
		{
			$extension = pathinfo($fullMatchPath, PATHINFO_EXTENSION);
			$this->setTemplateMatch($moduleName == null ? $requestPath : $moduleName.$requestPath, $pathMatchPattern, TemplateMatchType::Template, $extension);
			return true;
		}

		// no template match.
		return false;
	}

	/**
	 * @param $path
	 * @param $pathMatchPattern
	 * @param $matchType
	 * @param $extension
	 */
	private function setTemplateMatch($path, $pathMatchPattern, $matchType, $extension)
	{
		$templateMatch = new TemplateMatch($path);
		$templateMatch->setMatchRequest($pathMatchPattern);
		$templateMatch->setMatchType($matchType);
		$templateMatch->setMatchExtension($extension);
		$this->_templateMatch = $templateMatch;
	}

	/**
	 * Adds a trailing slash to the end of a path if one does not exist
	 * @param $path The path to normalize.
	 *
	 * @return string The normalized path.
	 */public function normalizeTrailingSlash($path)
	{
		$path = rtrim($path, '\\/').'/';
		return $path;
	}
}
