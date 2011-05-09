<?php

/**
 * Copyright 2004-2005 The Apache Software Foundation
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * A stemmer for Brazilian words.
 * @author	João Kramer
 * @author	Willy Barro (PHP Version)
 */
class BrazilianAnalyzer_Stemmer
{

	/**
	 * Changed term
	 */
	private $CT = null;
	private $R1 = null;
	private $R2 = null;
	private $RV = null;

	public function __construct()
	{
	}

	/**
	 * Stemms the given term to an unique <tt>discriminator</tt>.
	 *
	 * @param term  The term that should be stemmed.
	 * @return      Discriminator for <tt>term</tt>
	 */
	public function stem($term)
	{
		$altered = false; // altered the term
		// creates CT
		$this->createCT($term);

		if(!$this->isIndexable($this->CT)) {
			return null;
		}
		if(!$this->isStemmable($this->CT)) {
			return $this->CT;
		}

		$this->R1 = $this->getR1($this->CT);
		$this->R2 = $this->getR1($this->R1);
		$this->RV = $this->getRV($this->CT);
		$this->TERM = $term . ';' . $this->CT;

		$altered = $this->step1();
		if(!$altered) {
			$altered = $this->step2();
		}

		if($altered) {
			$this->step3();
		} else {
			$this->step4();
		}

		$this->step5();

		return $this->CT;
	}

	/**
	 * Checks a term if it can be processed correctly.
	 *
	 * @return  true if, and only if, the given term consists in letters.
	 */
	private function isStemmable($term)
	{
		for($i = 0; $i < strlen($term); $i++) {
			// Discard terms that contain non-letter characters.
			if(!ctype_alpha($term[$i])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks a term if it can be processed indexed.
	 *
	 * @return  true if it can be indexed
	 */
	private function isIndexable($term)
	{
		return (strlen($term) < 30 && strlen($term) > 2);
	}

	/**
	 * See if string is 'a','e','i','o','u'
	 *
	 * @return true if is vowel
	 */
	private function isVowel($value)
	{
		return ($value == 'a') ||
		($value == 'e') ||
		($value == 'i') ||
		($value == 'o') ||
		($value == 'u');
	}

	/**
	 * Gets R1
	 *
	 * R1 - is the region after the first non-vowel follwing a vowel,
	 *      or is the null region at the end of the word if there is
	 *      no such non-vowel.
	 *
	 * @return null or a string representing R1
	 */
	private function getR1($value)
	{
		$i = 0;
		$j = 0;

		// be-safe !!!
		if($value === null)
			return null;

		// find 1st vowel
		$i = strlen($value) - 1;
		for($j = 0; $j < $i; $j++) {
			if($this->isVowel($value[$j])) {
				break;
			}
		}

		if(!($j < $i)) {
			return null;
		}

		// find 1st non-vowel
		for(; $j < $i; $j++) {
			if(!($this->isVowel($value[$j]))) {
				break;
			}
		}

		if(!($j < $i)) {
			return null;
		}

		return substr($value, $j + 1);
	}

	/**
	 * Gets RV
	 *
	 * RV - IF the second letter is a consoant, RV is the region after
	 *      the next following vowel,
	 *
	 *      OR if the first two letters are vowels, RV is the region
	 *      after the next consoant,
	 *
	 *      AND otherwise (consoant-vowel case) RV is the region after
	 *      the third letter.
	 *
	 *      BUT RV is the end of the word if this positions cannot be
	 *      found.
	 *
	 * @return null or a string representing RV
	 */
	private function getRV($value)
	{
		$i = 0;
		$j = 0;

		// be-safe !!!
		if($value == null)
			return null;

		$i = strlen($value) - 1;

		// RV - IF the second letter is a consoant, RV is the region after
		//      the next following vowel,
		if(($i > 0) && $this->isVowel($value[1])) {
			// find 1st vowel
			for($j = 2; $j < $i; $j++) {
				if($this->isVowel($value[$j])) {
					break;
				}
			}

			if($j < $i) {
				return substr($value, $j + 1);
			}
		}


		// RV - OR if the first two letters are vowels, RV is the region
		//      after the next consoant,
		if(($i > 1) &&
			$this->isVowel($value[0]) &&
			$this->isVowel($value[1])) {
			// find 1st consoant
			for($j = 2; $j < $i; $j++) {
				if(!$this->isVowel($value[$j])) {
					break;
				}
			}

			if($j < $i) {
				return substr($value, $j + 1);
			}
		}

		// RV - AND otherwise (consoant-vowel case) RV is the region after
		//      the third letter.
		if($i > 2) {
			return substr($value, 3);
		}

		return null;
	}

	/**
	 * 1) Turn to lowercase
	 * 2) Accents were already removed on BrazilianAnalyzer_Analyzer::reset
	 *
	 * @return null or a string transformed
	 */
	private function changeTerm($value)
	{
		// be-safe !!!
		if($value == null)
			return null;

		return mb_strtolower($value);
	}

	/**
	 * Check if a string ends with a suffix
	 *
	 * @return true if the string ends with the specified suffix
	 */
	private function suffix($value, $suffix)
	{

		// be-safe !!!
		if(($value == null) || ($suffix == null)) {
			return false;
		}

		if(strlen($suffix) > strlen($value)) {
			return false;
		}

		return (substr($value, strlen($value) - strlen($suffix)) == $suffix);
	}

	/**
	 * Replace a string suffix by another
	 *
	 * @return the replaced String
	 */
	private function replaceSuffix($value, $toReplace, $changeTo)
	{
		$vvalue = null;

		// be-safe !!!
		if(($value == null) ||
			($toReplace == null) ||
			($changeTo == null)) {
			return $value;
		}

		$vvalue = $this->removeSuffix($value, $toReplace);

		if($value == $vvalue) {
			return $value;
		} else {
			return $vvalue . $changeTo;
		}
	}

	/**
	 * Remove a string suffix
	 *
	 * @return the String without the suffix
	 */
	private function removeSuffix($value, $toRemove)
	{
		// be-safe !!!
		if(($value == null) ||
			($toRemove == null) ||
			!$this->suffix($value, $toRemove)) {
			return $value;
		}

		return substr($value, 0, strlen($value) - strlen($toRemove));
	}

	/**
	 * See if a suffix is preceded by a String
	 *
	 * @return true if the suffix is preceded
	 */
	private function suffixPreceded($value, $suffix, $preceded)
	{
		// be-safe !!!
		if(($value == null) ||
			($suffix == null) ||
			($preceded == null) ||
			!$this->suffix($value, $suffix)) {
			return false;
		}

		return $this->suffix($this->removeSuffix($value, $suffix), $preceded);
	}

	/**
	 * Creates $this->CT (changed term) , substituting * 'ã' and 'õ' for 'a~' and 'o~'.
	 */
	private function createCT($term)
	{
		$this->CT = $this->changeTerm($term);

		if(strlen($this->CT) < 2)
			return;

		// if the first character is ... , remove it
		if(($this->CT[0] == '"') ||
			($this->CT[0] == '\'') ||
			($this->CT[0] == '-') ||
			($this->CT[0] == ',') ||
			($this->CT[0] == ';') ||
			($this->CT[0] == '.') ||
			($this->CT[0] == '?') ||
			($this->CT[0] == '!')
		) {
			$this->CT = substr($this->CT, $this->CT[1]);
		}

		if(strlen($this->CT) < 2)
			return;

		// if the last character is ... , remove it
		if(($this->CT[strlen($this->CT) - 1] == '-') ||
			($this->CT[strlen($this->CT) - 1] == ',') ||
			($this->CT[strlen($this->CT) - 1] == ';') ||
			($this->CT[strlen($this->CT) - 1] == '.') ||
			($this->CT[strlen($this->CT) - 1] == '?') ||
			($this->CT[strlen($this->CT) - 1] == '!') ||
			($this->CT[strlen($this->CT) - 1] == '\'') ||
			($this->CT[strlen($this->CT) - 1] == '"')
		) {
			$this->CT = substr($this->CT, 0, strlen($this->CT) - 1);
		}
	}

	/**
	 * Standart suffix removal.
	 * Search for the longest among the following suffixes, and perform
	 * the following actions:
	 *
	 * @return false if no ending was removed
	 */
	private function step1()
	{
		if($this->CT == null)
			return false;

		// suffix lenght = 7
		if($this->suffix($this->CT, "uciones") && $this->suffix($this->R2, "uciones")) {
			$this->CT = $this->replaceSuffix($this->CT, "uciones", "u");
			return true;
		}

		// suffix lenght = 6
		if(strlen($this->CT) >= 6) {
			if($this->suffix($this->CT, "imentos") && $this->suffix($this->R2, "imentos")) {
				$this->CT = $this->removeSuffix($this->CT, "imentos");
				return true;
			}
			if($this->suffix($this->CT, "amentos") && $this->suffix($this->R2, "amentos")) {
				$this->CT = $this->removeSuffix($this->CT, "amentos");
				return true;
			}
			if($this->suffix($this->CT, "adores") && $this->suffix($this->R2, "adores")) {
				$this->CT = $this->removeSuffix($this->CT, "adores");
				return true;
			}
			if($this->suffix($this->CT, "adoras") && $this->suffix($this->R2, "adoras")) {
				$this->CT = $this->removeSuffix($this->CT, "adoras");
				return true;
			}
			if($this->suffix($this->CT, "logias") && $this->suffix($this->R2, "logias")) {
				$this->replaceSuffix($this->CT, "logias", "log");
				return true;
			}
			if($this->suffix($this->CT, "encias") && $this->suffix($this->R2, "encias")) {
				$this->CT = $this->replaceSuffix($this->CT, "encias", "ente");
				return true;
			}
			if($this->suffix($this->CT, "amente") && $this->suffix(R1, "amente")) {
				$this->CT = $this->removeSuffix($this->CT, "amente");
				return true;
			}
			if($this->suffix($this->CT, "idades") && $this->suffix($this->R2, "idades")) {
				$this->CT = $this->removeSuffix($this->CT, "idades");
				return true;
			}
		}

		// suffix lenght = 5
		if(strlen($this->CT) >= 5) {
			if($this->suffix($this->CT, "acoes") && $this->suffix($this->R2, "acoes")) {
				$this->CT = $this->removeSuffix($this->CT, "acoes");
				return true;
			}
			if($this->suffix($this->CT, "imento") && $this->suffix($this->R2, "imento")) {
				$this->CT = $this->removeSuffix($this->CT, "imento");
				return true;
			}
			if($this->suffix($this->CT, "amento") && $this->suffix($this->R2, "amento")) {
				$this->CT = $this->removeSuffix($this->CT, "amento");
				return true;
			}
			if($this->suffix($this->CT, "adora") && $this->suffix($this->R2, "adora")) {
				$this->CT = $this->removeSuffix($this->CT, "adora");
				return true;
			}
			if($this->suffix($this->CT, "ismos") && $this->suffix($this->R2, "ismos")) {
				$this->CT = $this->removeSuffix($this->CT, "ismos");
				return true;
			}
			if($this->suffix($this->CT, "istas") && $this->suffix($this->R2, "istas")) {
				$this->CT = $this->removeSuffix($this->CT, "istas");
				return true;
			}
			if($this->suffix($this->CT, "logia") && $this->suffix($this->R2, "logia")) {
				$this->CT = $this->replaceSuffix($this->CT, "logia", "log");
				return true;
			}
			if($this->suffix($this->CT, "ucion") && $this->suffix($this->R2, "ucion")) {
				$this->CT = $this->replaceSuffix($this->CT, "ucion", "u");
				return true;
			}
			if($this->suffix($this->CT, "encia") && $this->suffix($this->R2, "encia")) {
				$this->CT = $this->replaceSuffix($this->CT, "encia", "ente");
				return true;
			}
			if($this->suffix($this->CT, "mente") && $this->suffix($this->R2, "mente")) {
				$this->CT = $this->removeSuffix($this->CT, "mente");
				return true;
			}
			if($this->suffix($this->CT, "idade") && $this->suffix($this->R2, "idade")) {
				$this->CT = $this->removeSuffix($this->CT, "idade");
				return true;
			}
		}

		// suffix lenght = 4
		if(strlen($this->CT) >= 4) {
			if($this->suffix($this->CT, "acao") && $this->suffix($this->R2, "acao")) {
				$this->CT = $this->removeSuffix($this->CT, "acao");
				return true;
			}
			if($this->suffix($this->CT, "ezas") && $this->suffix($this->R2, "ezas")) {
				$this->CT = $this->removeSuffix($this->CT, "ezas");
				return true;
			}
			if($this->suffix($this->CT, "icos") && $this->suffix($this->R2, "icos")) {
				$this->CT = $this->removeSuffix($this->CT, "icos");
				return true;
			}
			if($this->suffix($this->CT, "icas") && $this->suffix($this->R2, "icas")) {
				$this->CT = $this->removeSuffix($this->CT, "icas");
				return true;
			}
			if($this->suffix($this->CT, "ismo") && $this->suffix($this->R2, "ismo")) {
				$this->CT = $this->removeSuffix($this->CT, "ismo");
				return true;
			}
			if($this->suffix($this->CT, "avel") && $this->suffix($this->R2, "avel")) {
				$this->CT = $this->removeSuffix($this->CT, "avel");
				return true;
			}
			if($this->suffix($this->CT, "ivel") && $this->suffix($this->R2, "ivel")) {
				$this->CT = $this->removeSuffix($this->CT, "ivel");
				return true;
			}
			if($this->suffix($this->CT, "ista") && $this->suffix($this->R2, "ista")) {
				$this->CT = $this->removeSuffix($this->CT, "ista");
				return true;
			}
			if($this->suffix($this->CT, "osos") && $this->suffix($this->R2, "osos")) {
				$this->CT = $this->removeSuffix($this->CT, "osos");
				return true;
			}
			if($this->suffix($this->CT, "osas") && $this->suffix($this->R2, "osas")) {
				$this->CT = $this->removeSuffix($this->CT, "osas");
				return true;
			}
			if($this->suffix($this->CT, "ador") && $this->suffix($this->R2, "ador")) {
				$this->CT = $this->removeSuffix($this->CT, "ador");
				return true;
			}
			if($this->suffix($this->CT, "ivas") && $this->suffix($this->R2, "ivas")) {
				$this->CT = $this->removeSuffix($this->CT, "ivas");
				return true;
			}
			if($this->suffix($this->CT, "ivos") && $this->suffix($this->R2, "ivos")) {
				$this->CT = $this->removeSuffix($this->CT, "ivos");
				return true;
			}
			if($this->suffix($this->CT, "iras") &&
				$this->suffix($this->RV, "iras") &&
				$this->suffixPreceded($this->CT, "iras", "e")) {
				$this->CT = $this->replaceSuffix($this->CT, "iras", "ir");
				return true;
			}
		}

		// suffix lenght = 3
		if(strlen($this->CT) >= 3) {
			if($this->suffix($this->CT, "eza") && $this->suffix($this->R2, "eza")) {
				$this->CT = $this->removeSuffix($this->CT, "eza");
				return true;
			}
			if($this->suffix($this->CT, "ico") && $this->suffix($this->R2, "ico")) {
				$this->CT = $this->removeSuffix($this->CT, "ico");
				return true;
			}
			if($this->suffix($this->CT, "ica") && $this->suffix($this->R2, "ica")) {
				$this->CT = $this->removeSuffix($this->CT, "ica");
				return true;
			}
			if($this->suffix($this->CT, "oso") && $this->suffix($this->R2, "oso")) {
				$this->CT = $this->removeSuffix($this->CT, "oso");
				return true;
			}
			if($this->suffix($this->CT, "osa") && $this->suffix($this->R2, "osa")) {
				$this->CT = $this->removeSuffix($this->CT, "osa");
				return true;
			}
			if($this->suffix($this->CT, "iva") && $this->suffix($this->R2, "iva")) {
				$this->CT = $this->removeSuffix($this->CT, "iva");
				return true;
			}
			if($this->suffix($this->CT, "ivo") && $this->suffix($this->R2, "ivo")) {
				$this->CT = $this->removeSuffix($this->CT, "ivo");
				return true;
			}
			if($this->suffix($this->CT, "ira") &&
				$this->suffix($this->RV, "ira") &&
				$this->suffixPreceded($this->CT, "ira", "e")) {
				$this->CT = $this->replaceSuffix($this->CT, "ira", "ir");
				return true;
			}
		}

		// no ending was removed by step1
		return false;
	}

	/**
	 * Verb suffixes.
	 *
	 * Search for the longest among the following suffixes in RV,
	 * and if found, delete.
	 *
	 * @return false if no ending was removed
	 */
	private function step2()
	{
		if($this->RV == null)
			return false;

		// suffix lenght = 7
		if(strlen($this->RV) >= 7) {
			if($this->suffix($this->RV, "issemos")) {
				$this->CT = $this->removeSuffix($this->CT, "issemos");
				return true;
			}
			if($this->suffix($this->RV, "essemos")) {
				$this->CT = $this->removeSuffix($this->CT, "essemos");
				return true;
			}
			if($this->suffix($this->RV, "assemos")) {
				$this->CT = $this->removeSuffix($this->CT, "assemos");
				return true;
			}
			if($this->suffix($this->RV, "ariamos")) {
				$this->CT = $this->removeSuffix($this->CT, "ariamos");
				return true;
			}
			if($this->suffix($this->RV, "eriamos")) {
				$this->CT = $this->removeSuffix($this->CT, "eriamos");
				return true;
			}
			if($this->suffix($this->RV, "iriamos")) {
				$this->CT = $this->removeSuffix($this->CT, "iriamos");
				return true;
			}
		}

		// suffix lenght = 6
		if(strlen($this->RV) >= 6) {
			if($this->suffix($this->RV, "iremos")) {
				$this->CT = $this->removeSuffix($this->CT, "iremos");
				return true;
			}
			if($this->suffix($this->RV, "eremos")) {
				$this->CT = $this->removeSuffix($this->CT, "eremos");
				return true;
			}
			if($this->suffix($this->RV, "aremos")) {
				$this->CT = $this->removeSuffix($this->CT, "aremos");
				return true;
			}
			if($this->suffix($this->RV, "avamos")) {
				$this->CT = $this->removeSuffix($this->CT, "avamos");
				return true;
			}
			if($this->suffix($this->RV, "iramos")) {
				$this->CT = $this->removeSuffix($this->CT, "iramos");
				return true;
			}
			if($this->suffix($this->RV, "eramos")) {
				$this->CT = $this->removeSuffix($this->CT, "eramos");
				return true;
			}
			if($this->suffix($this->RV, "aramos")) {
				$this->CT = $this->removeSuffix($this->CT, "aramos");
				return true;
			}
			if($this->suffix($this->RV, "asseis")) {
				$this->CT = $this->removeSuffix($this->CT, "asseis");
				return true;
			}
			if($this->suffix($this->RV, "esseis")) {
				$this->CT = $this->removeSuffix($this->CT, "esseis");
				return true;
			}
			if($this->suffix($this->RV, "isseis")) {
				$this->CT = $this->removeSuffix($this->CT, "isseis");
				return true;
			}
			if($this->suffix($this->RV, "arieis")) {
				$this->CT = $this->removeSuffix($this->CT, "arieis");
				return true;
			}
			if($this->suffix($this->RV, "erieis")) {
				$this->CT = $this->removeSuffix($this->CT, "erieis");
				return true;
			}
			if($this->suffix($this->RV, "irieis")) {
				$this->CT = $this->removeSuffix($this->CT, "irieis");
				return true;
			}
		}


		// suffix lenght = 5
		if(strlen($this->RV) >= 5) {
			if($this->suffix($this->RV, "irmos")) {
				$this->CT = $this->removeSuffix($this->CT, "irmos");
				return true;
			}
			if($this->suffix($this->RV, "iamos")) {
				$this->CT = $this->removeSuffix($this->CT, "iamos");
				return true;
			}
			if($this->suffix($this->RV, "armos")) {
				$this->CT = $this->removeSuffix($this->CT, "armos");
				return true;
			}
			if($this->suffix($this->RV, "ermos")) {
				$this->CT = $this->removeSuffix($this->CT, "ermos");
				return true;
			}
			if($this->suffix($this->RV, "areis")) {
				$this->CT = $this->removeSuffix($this->CT, "areis");
				return true;
			}
			if($this->suffix($this->RV, "ereis")) {
				$this->CT = $this->removeSuffix($this->CT, "ereis");
				return true;
			}
			if($this->suffix($this->RV, "ireis")) {
				$this->CT = $this->removeSuffix($this->CT, "ireis");
				return true;
			}
			if($this->suffix($this->RV, "asses")) {
				$this->CT = $this->removeSuffix($this->CT, "asses");
				return true;
			}
			if($this->suffix($this->RV, "esses")) {
				$this->CT = $this->removeSuffix($this->CT, "esses");
				return true;
			}
			if($this->suffix($this->RV, "isses")) {
				$this->CT = $this->removeSuffix($this->CT, "isses");
				return true;
			}
			if($this->suffix($this->RV, "astes")) {
				$this->CT = $this->removeSuffix($this->CT, "astes");
				return true;
			}
			if($this->suffix($this->RV, "assem")) {
				$this->CT = $this->removeSuffix($this->CT, "assem");
				return true;
			}
			if($this->suffix($this->RV, "essem")) {
				$this->CT = $this->removeSuffix($this->CT, "essem");
				return true;
			}
			if($this->suffix($this->RV, "issem")) {
				$this->CT = $this->removeSuffix($this->CT, "issem");
				return true;
			}
			if($this->suffix($this->RV, "ardes")) {
				$this->CT = $this->removeSuffix($this->CT, "ardes");
				return true;
			}
			if($this->suffix($this->RV, "erdes")) {
				$this->CT = $this->removeSuffix($this->CT, "erdes");
				return true;
			}
			if($this->suffix($this->RV, "irdes")) {
				$this->CT = $this->removeSuffix($this->CT, "irdes");
				return true;
			}
			if($this->suffix($this->RV, "ariam")) {
				$this->CT = $this->removeSuffix($this->CT, "ariam");
				return true;
			}
			if($this->suffix($this->RV, "eriam")) {
				$this->CT = $this->removeSuffix($this->CT, "eriam");
				return true;
			}
			if($this->suffix($this->RV, "iriam")) {
				$this->CT = $this->removeSuffix($this->CT, "iriam");
				return true;
			}
			if($this->suffix($this->RV, "arias")) {
				$this->CT = $this->removeSuffix($this->CT, "arias");
				return true;
			}
			if($this->suffix($this->RV, "erias")) {
				$this->CT = $this->removeSuffix($this->CT, "erias");
				return true;
			}
			if($this->suffix($this->RV, "irias")) {
				$this->CT = $this->removeSuffix($this->CT, "irias");
				return true;
			}
			if($this->suffix($this->RV, "estes")) {
				$this->CT = $this->removeSuffix($this->CT, "estes");
				return true;
			}
			if($this->suffix($this->RV, "istes")) {
				$this->CT = $this->removeSuffix($this->CT, "istes");
				return true;
			}
			if($this->suffix($this->RV, "areis")) {
				$this->CT = $this->removeSuffix($this->CT, "areis");
				return true;
			}
			if($this->suffix($this->RV, "aveis")) {
				$this->CT = $this->removeSuffix($this->CT, "aveis");
				return true;
			}
		}

		// suffix lenght = 4
		if(strlen($this->RV) >= 4) {
			if($this->suffix($this->RV, "aria")) {
				$this->CT = $this->removeSuffix($this->CT, "aria");
				return true;
			}
			if($this->suffix($this->RV, "eria")) {
				$this->CT = $this->removeSuffix($this->CT, "eria");
				return true;
			}
			if($this->suffix($this->RV, "iria")) {
				$this->CT = $this->removeSuffix($this->CT, "iria");
				return true;
			}
			if($this->suffix($this->RV, "asse")) {
				$this->CT = $this->removeSuffix($this->CT, "asse");
				return true;
			}
			if($this->suffix($this->RV, "esse")) {
				$this->CT = $this->removeSuffix($this->CT, "esse");
				return true;
			}
			if($this->suffix($this->RV, "isse")) {
				$this->CT = $this->removeSuffix($this->CT, "isse");
				return true;
			}
			if($this->suffix($this->RV, "aste")) {
				$this->CT = $this->removeSuffix($this->CT, "aste");
				return true;
			}
			if($this->suffix($this->RV, "este")) {
				$this->CT = $this->removeSuffix($this->CT, "este");
				return true;
			}
			if($this->suffix($this->RV, "iste")) {
				$this->CT = $this->removeSuffix($this->CT, "iste");
				return true;
			}
			if($this->suffix($this->RV, "arei")) {
				$this->CT = $this->removeSuffix($this->CT, "arei");
				return true;
			}
			if($this->suffix($this->RV, "erei")) {
				$this->CT = $this->removeSuffix($this->CT, "erei");
				return true;
			}
			if($this->suffix($this->RV, "irei")) {
				$this->CT = $this->removeSuffix($this->CT, "irei");
				return true;
			}
			if($this->suffix($this->RV, "aram")) {
				$this->CT = $this->removeSuffix($this->CT, "aram");
				return true;
			}
			if($this->suffix($this->RV, "eram")) {
				$this->CT = $this->removeSuffix($this->CT, "eram");
				return true;
			}
			if($this->suffix($this->RV, "iram")) {
				$this->CT = $this->removeSuffix($this->CT, "iram");
				return true;
			}
			if($this->suffix($this->RV, "avam")) {
				$this->CT = $this->removeSuffix($this->CT, "avam");
				return true;
			}
			if($this->suffix($this->RV, "arem")) {
				$this->CT = $this->removeSuffix($this->CT, "arem");
				return true;
			}
			if($this->suffix($this->RV, "erem")) {
				$this->CT = $this->removeSuffix($this->CT, "erem");
				return true;
			}
			if($this->suffix($this->RV, "irem")) {
				$this->CT = $this->removeSuffix($this->CT, "irem");
				return true;
			}
			if($this->suffix($this->RV, "ando")) {
				$this->CT = $this->removeSuffix($this->CT, "ando");
				return true;
			}
			if($this->suffix($this->RV, "endo")) {
				$this->CT = $this->removeSuffix($this->CT, "endo");
				return true;
			}
			if($this->suffix($this->RV, "indo")) {
				$this->CT = $this->removeSuffix($this->CT, "indo");
				return true;
			}
			if($this->suffix($this->RV, "arao")) {
				$this->CT = $this->removeSuffix($this->CT, "arao");
				return true;
			}
			if($this->suffix($this->RV, "erao")) {
				$this->CT = $this->removeSuffix($this->CT, "erao");
				return true;
			}
			if($this->suffix($this->RV, "irao")) {
				$this->CT = $this->removeSuffix($this->CT, "irao");
				return true;
			}
			if($this->suffix($this->RV, "adas")) {
				$this->CT = $this->removeSuffix($this->CT, "adas");
				return true;
			}
			if($this->suffix($this->RV, "idas")) {
				$this->CT = $this->removeSuffix($this->CT, "idas");
				return true;
			}
			if($this->suffix($this->RV, "aras")) {
				$this->CT = $this->removeSuffix($this->CT, "aras");
				return true;
			}
			if($this->suffix($this->RV, "eras")) {
				$this->CT = $this->removeSuffix($this->CT, "eras");
				return true;
			}
			if($this->suffix($this->RV, "iras")) {
				$this->CT = $this->removeSuffix($this->CT, "iras");
				return true;
			}
			if($this->suffix($this->RV, "avas")) {
				$this->CT = $this->removeSuffix($this->CT, "avas");
				return true;
			}
			if($this->suffix($this->RV, "ares")) {
				$this->CT = $this->removeSuffix($this->CT, "ares");
				return true;
			}
			if($this->suffix($this->RV, "eres")) {
				$this->CT = $this->removeSuffix($this->CT, "eres");
				return true;
			}
			if($this->suffix($this->RV, "ires")) {
				$this->CT = $this->removeSuffix($this->CT, "ires");
				return true;
			}
			if($this->suffix($this->RV, "ados")) {
				$this->CT = $this->removeSuffix($this->CT, "ados");
				return true;
			}
			if($this->suffix($this->RV, "idos")) {
				$this->CT = $this->removeSuffix($this->CT, "idos");
				return true;
			}
			if($this->suffix($this->RV, "amos")) {
				$this->CT = $this->removeSuffix($this->CT, "amos");
				return true;
			}
			if($this->suffix($this->RV, "emos")) {
				$this->CT = $this->removeSuffix($this->CT, "emos");
				return true;
			}
			if($this->suffix($this->RV, "imos")) {
				$this->CT = $this->removeSuffix($this->CT, "imos");
				return true;
			}
			if($this->suffix($this->RV, "iras")) {
				$this->CT = $this->removeSuffix($this->CT, "iras");
				return true;
			}
			if($this->suffix($this->RV, "ieis")) {
				$this->CT = $this->removeSuffix($this->CT, "ieis");
				return true;
			}
		}

		// suffix lenght = 3
		if(strlen($this->RV) >= 3) {
			if($this->suffix($this->RV, "ada")) {
				$this->CT = $this->removeSuffix($this->CT, "ada");
				return true;
			}
			if($this->suffix($this->RV, "ida")) {
				$this->CT = $this->removeSuffix($this->CT, "ida");
				return true;
			}
			if($this->suffix($this->RV, "ara")) {
				$this->CT = $this->removeSuffix($this->CT, "ara");
				return true;
			}
			if($this->suffix($this->RV, "era")) {
				$this->CT = $this->removeSuffix($this->CT, "era");
				return true;
			}
			if($this->suffix($this->RV, "ira")) {
				$this->CT = $this->removeSuffix($this->CT, "ava");
				return true;
			}
			if($this->suffix($this->RV, "iam")) {
				$this->CT = $this->removeSuffix($this->CT, "iam");
				return true;
			}
			if($this->suffix($this->RV, "ado")) {
				$this->CT = $this->removeSuffix($this->CT, "ado");
				return true;
			}
			if($this->suffix($this->RV, "ido")) {
				$this->CT = $this->removeSuffix($this->CT, "ido");
				return true;
			}
			if($this->suffix($this->RV, "ias")) {
				$this->CT = $this->removeSuffix($this->CT, "ias");
				return true;
			}
			if($this->suffix($this->RV, "ais")) {
				$this->CT = $this->removeSuffix($this->CT, "ais");
				return true;
			}
			if($this->suffix($this->RV, "eis")) {
				$this->CT = $this->removeSuffix($this->CT, "eis");
				return true;
			}
			if($this->suffix($this->RV, "ira")) {
				$this->CT = $this->removeSuffix($this->CT, "ira");
				return true;
			}
			if($this->suffix($this->RV, "ear")) {
				$this->CT = $this->removeSuffix($this->CT, "ear");
				return true;
			}
		}

		// suffix lenght = 2
		if(strlen($this->RV) >= 2) {
			if($this->suffix($this->RV, "ia")) {
				$this->CT = $this->removeSuffix($this->CT, "ia");
				return true;
			}
			if($this->suffix($this->RV, "ei")) {
				$this->CT = $this->removeSuffix($this->CT, "ei");
				return true;
			}
			if($this->suffix($this->RV, "am")) {
				$this->CT = $this->removeSuffix($this->CT, "am");
				return true;
			}
			if($this->suffix($this->RV, "em")) {
				$this->CT = $this->removeSuffix($this->CT, "em");
				return true;
			}
			if($this->suffix($this->RV, "ar")) {
				$this->CT = $this->removeSuffix($this->CT, "ar");
				return true;
			}
			if($this->suffix($this->RV, "er")) {
				$this->CT = $this->removeSuffix($this->CT, "er");
				return true;
			}
			if($this->suffix($this->RV, "ir")) {
				$this->CT = $this->removeSuffix($this->CT, "ir");
				return true;
			}
			if($this->suffix($this->RV, "as")) {
				$this->CT = $this->removeSuffix($this->CT, "as");
				return true;
			}
			if($this->suffix($this->RV, "es")) {
				$this->CT = $this->removeSuffix($this->CT, "es");
				return true;
			}
			if($this->suffix($this->RV, "is")) {
				$this->CT = $this->removeSuffix($this->CT, "is");
				return true;
			}
			if($this->suffix($this->RV, "eu")) {
				$this->CT = $this->removeSuffix($this->CT, "eu");
				return true;
			}
			if($this->suffix($this->RV, "iu")) {
				$this->CT = $this->removeSuffix($this->CT, "iu");
				return true;
			}
			if($this->suffix($this->RV, "iu")) {
				$this->CT = $this->removeSuffix($this->CT, "iu");
				return true;
			}
			if($this->suffix($this->RV, "ou")) {
				$this->CT = $this->removeSuffix($this->CT, "ou");
				return true;
			}
		}

		// no ending was removed by step2
		return false;
	}

	/**
	 * Delete suffix 'i' if in RV and preceded by 'c'
	 *
	 */
	private function step3()
	{
		if($this->RV == null)
			return;

		if($this->suffix($this->RV, "i") && $this->suffixPreceded($this->RV, "i", "c")) {
			$this->CT = $this->removeSuffix($this->CT, "i");
		}
	}

	/**
	 * Residual suffix
	 *
	 * If the word ends with one of the suffixes (os a i o á í ó)
	 * in RV, delete it
	 *
	 */
	private function step4()
	{
		if($this->RV == null)
			return;

		if($this->suffix($this->RV, "os")) {
			$this->CT = $this->removeSuffix($this->CT, "os");
			return;
		}
		if($this->suffix($this->RV, "a")) {
			$this->CT = $this->removeSuffix($this->CT, "a");
			return;
		}
		if($this->suffix($this->RV, "i")) {
			$this->CT = $this->removeSuffix($this->CT, "i");
			return;
		}
		if($this->suffix($this->RV, "o")) {
			$this->CT = $this->removeSuffix($this->CT, "o");
			return;
		}
	}

	/**
	 * If the word ends with one of ( e é ê) in RV,delete it,
	 * and if preceded by 'gu' (or 'ci') with the 'u' (or 'i') in RV,
	 * delete the 'u' (or 'i')
	 *
	 * Or if the word ends ç remove the cedilha
	 *
	 */
	private function step5()
	{
		if($this->RV == null)
			return;

		if($this->suffix($this->RV, "e")) {
			if($this->suffixPreceded($this->RV, "e", "gu")) {
				$this->CT = $this->removeSuffix($this->CT, "e");
				$this->CT = $this->removeSuffix($this->CT, "u");
				return;
			}

			if($this->suffixPreceded($this->RV, "e", "ci")) {
				$this->CT = $this->removeSuffix($this->CT, "e");
				$this->CT = $this->removeSuffix($this->CT, "i");
				return;
			}

			$this->CT = $this->removeSuffix($this->CT, "e");
			return;
		}
	}

}