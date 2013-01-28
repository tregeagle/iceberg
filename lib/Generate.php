<?php

namespace commands;

use DateTime;
use DateTimeZone;

use Twig_Environment;
use Twig_Loader_Filesystem;

use iceberg\hook\Hook;
use iceberg\config\Config;
use iceberg\cmd\AbstractCommand;
use iceberg\shell\ArgumentParser;

use iceberg\cmd\exceptions\InvalidInputException;
use iceberg\cmd\exceptions\InputDataNotFoundException;
use iceberg\cmd\exceptions\InputFileNotGivenException;

use iceberg\layout\exceptions\LayoutNotGivenException;
use iceberg\layout\exceptions\LayoutFileDoesNotExistException;

class Object { }
class Generate extends AbstractCommand {

	public static function run() {

		$arguments = ArgumentParser::getInstance();
		
		switch (true) {
			case $arguments->file_val:
				$inputFilePath = $arguments->file;
				break;
			
			case $arguments->name_val:
				$inputFilePath = str_replace("{article}", $arguments->name, Config::getVal("article", "input", true));
				break;
			
			default:
				throw new InputFileNotGivenException("Article file name or path was not given.");
				break;
		}
		
		$inputFileContent = @file_get_contents($inputFilePath);
		if (!$inputFileContent) {
			throw new InputFileNotGivenException("Article file does not exist or could not be opened.");
		}

		$article = new Object;
		$article->author = Config::getVal("general", "author", true);
		$article->content = trim(preg_replace("/@([a-zA-Z]+):\s(.*)\n?/", "", $inputFileContent));

		preg_match_all("/@([a-zA-Z]+):\s(.*)\n?/", $inputFileContent, $metadata, PREG_SET_ORDER);
		foreach ($metadata as $detail) {
			$article->$detail[1] = trim($detail[2]);
		}

		if (!isset($article->title)) {
			throw new InputDataNotFoundException("Article does not have a title.");
		}

		if (!isset($article->slug)) {
			$article->slug = strtolower($article->title);
		}
		$article->slug = str_replace(" ", "-", preg_replace("/[^a-zA-Z0-9_\s]/", "", $article->slug));

		if (isset($article->date)) {
			$timestamp = strtotime($article->date);
			if (!$timestamp) {
				throw new InvalidInputException("Invalid article date. Make sure the date is a valid PHP date.");
			}
			$article->date = new DateTime("@{$timestamp}");
		} else {
			$article->date = new DateTime();
		}
		$article->date->setTimezone(new DateTimeZone(date_default_timezone_get()));

		switch (true) {

			case $arguments->output_val:
				$outputFilePath = $arguments->output;
				break;

			case isset($article->output):
				$outputFilePath = $article->output;
				break;

			default:
				$outputFilePath = str_replace("{slug}", $article->slug, Config::getVal("article", "output", true));
				break;
		}
		@mkdir(dirname($outputFilePath), 0777, true);

		switch (true) {

			case $arguments->layout_val:
				$layoutFile = $argument->layout;
				break;

			case isset($article->layout):
				$layoutFile = $article->layout;
				break;

			default:
				throw new LayoutNotGivenException("Layout to be used was not given.");
				break;

		}

		$layoutFilePath = str_replace("{layout}", $layoutFile, Config::getVal("article", "layout", true));
		if (!file_exists($layoutFilePath)) {
			throw new LayoutFileDoesNotExistException("Layout file does not exist.");
		}

		$layoutDirectory = dirname($layoutFilePath);
		$layoutLoader = new Twig_Loader_Filesystem($layoutDirectory);

		$layoutParser = new Twig_Environment($layoutLoader);
		$layoutRendered = $layoutParser->render(end(explode("/", $layoutFilePath)), (array) $article);

		$outputFileWritten = @file_put_contents($outputFilePath, $layoutRendered);
		if (!$outputFileWritten) {
			throw new InvalidInputException("Could not write generate output to file.");
		}

		Hook::setEnvironmentData("generate", "article", $article);
	}

}
