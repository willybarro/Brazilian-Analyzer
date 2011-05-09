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
 * Analyzer for Brazilian language. Supports an external list of stopwords (words that
 * will not be indexed at all) and an external list of exclusions (word that will
 * not be stemmed, but indexed).
 *
 * @author	João Kramer
 * @author	Willy Barro (PHP Version)
 */

final class BrazilianAnalyzer_Analyzer extends Zend_Search_Lucene_Analysis_Analyzer {

	/**
	 * List of typical Brazilian stopwords.
	 */
	private $br_stop_words = array(
      'a', 'ainda', 'alem', 'ambas', 'ambos', 'antes',
      'ao', 'aonde', 'aos', 'apos', 'aquele', 'aqueles',
      'as', 'assim', 'com', 'como', 'contra', 'contudo',
      'cuja', 'cujas', 'cujo', 'cujos', 'da', 'das', 'de',
      'dela', 'dele', 'deles', 'demais', 'depois', 'desde',
      'desta', 'deste', 'dispoe', 'dispoem', 'diversa',
      'diversas', 'diversos', 'do', 'dos', 'durante', 'e',
      'ela', 'elas', 'ele', 'eles', 'em', 'entao', 'entre',
      'essa', 'essas', 'esse', 'esses', 'esta', 'estas',
      'este', 'estes', 'ha', 'isso', 'isto', 'logo', 'mais',
      'mas', 'mediante', 'menos', 'mesma', 'mesmas', 'mesmo',
      'mesmos', 'na', 'nas', 'nao', 'nas', 'nem', 'nesse', 'neste',
      'nos', 'o', 'os', 'ou', 'outra', 'outras', 'outro', 'outros',
      'pelas', 'pelas', 'pelo', 'pelos', 'perante', 'pois', 'por',
      'porque', 'portanto', 'proprio', 'propios', 'quais', 'qual',
      'qualquer', 'quando', 'quanto', 'que', 'quem', 'quer', 'se',
      'seja', 'sem', 'sendo', 'seu', 'seus', 'sob', 'sobre', 'sua',
      'suas', 'tal', 'tambem', 'teu', 'teus', 'toda', 'todas', 'todo',
      'todos', 'tua', 'tuas', 'tudo', 'um', 'uma', 'umas', 'uns');

	/**
	 * Exclusion list
	 */
	private $br_exclusion_list = array();


	/**
	 * Contains the filters used
	 */
	private $_filters = array();

	/**
	 * Current position in a stream
	 *
	 * @var integer
	 */
	private $_position;

	/**
	 * Defines
	 * @return void
	 */
	public function reset()
	{
		$this->_position = 0;

		if ($this->_input === null) return;

		// Convert to ASCII and remove unused chars (preg)
		$this->_input = iconv($this->_encoding, 'ASCII//TRANSLIT', $this->_input);
		$this->_input = preg_replace('/[^\s-a-zA-Z0-9]+/', '', $this->_input);
		$this->_encoding = 'ASCII';
	}

	/**
	 * Builds an analyzer with the default stop words (@link $this->br_stop_words).
	 * @param array $stopwords Array of StopWords
	 * @param array $exclusionlist Array of words which will be stored but not tokenized.
	 */
	public function __construct( array $stopwords = array(), array $exclusionlist = array() )
	{
		$stopwords			= array_merge($this->br_stop_words, $stopwords);
		$exclusionlist	= array_merge($this->br_exclusion_list, $exclusionlist);
		
		// Convert to lowercase
		$this->addFilter( new Zend_Search_Lucene_Analysis_TokenFilter_LowerCaseUtf8() );

		// Stemm
		$this->addFilter( new BrazilianAnalyzer_StemFilter( $exclusionlist ) );

		// Apply Stop Words
		$this->addFilter( new Zend_Search_Lucene_Analysis_TokenFilter_StopWords( $stopwords ) );
	}

	/**
	 * Creates a TokenStream which tokenizes all the text passed.
	 *
	 * @return Zend_Search_Lucene_Analysis_Token A Token filtered by every filter
	 * on self::$_filters Array.
	 */
	public function normalize(Zend_Search_Lucene_Analysis_Token $token)
	{
		foreach ($this->_filters as $filter) {
			$token = $filter->normalize($token);
		}

		return $token;
	}

	public function nextToken()
	{
		if ($this->_input === null) return null;

		while($this->_position < strlen($this->_input)) {
			// Skips white spaces
			while($this->_position < strlen($this->_input) &&
						!ctype_alnum( $this->_input[$this->_position] )
			) {
				$this->_position++;
			}

			$termStartPosition = $this->_position;

			// Read token
			while($this->_position < strlen($this->_input) &&
						ctype_alnum( $this->_input[$this->_position] )
			) {
				$this->_position++;
			}

			// Empty token
			if($this->_position == $termStartPosition) return null;

			// Generate Token
			$token = $this->normalize(
				new Zend_Search_Lucene_Analysis_Token(
					substr($this->_input,
								 $termStartPosition,
								 $this->_position - $termStartPosition
					),
					$termStartPosition,
					$this->_position)
				);

			// 
			if($token !== null) return $token;
		}
	}

	/**
	 * Adds a filter to be executed at every normalization
	 * @param Zend_Search_Lucene_Analysis_TokenFilter $filter
	 */
	private function addFilter(Zend_Search_Lucene_Analysis_TokenFilter $filter)
	{
			$this->_filters[] = $filter;
	}
}
