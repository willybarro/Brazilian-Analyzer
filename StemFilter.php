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
 * Based on GermanStemFilter
 *
 * @author João Kramer
 * @author	Willy Barro (PHP Version)
 */
final class BrazilianAnalyzer_StemFilter extends Zend_Search_Lucene_Analysis_TokenFilter {

  /**
   * Exclusion list
   */
  public $_exclusions = null;
	
	/**
	 * Stemmer instance
	 */
	public $_stemmer = null;

  public function __construct( array $exclusionTable = array() ) {
		if(!empty($exclusionTable)) {
			$this->_exclusions = $exclusionTable;
		}

		// Instantiate the stemmer
		$this->_stemmer = new BrazilianAnalyzer_Stemmer();
  }

  public function normalize(Zend_Search_Lucene_Analysis_Token $srcToken)
	{
		if($srcToken === null) return null;

		$token = null;

		if( $srcToken === null ) {
			return null;
		} else
		// Not Stemmed, but indexed.
		if( !empty($this->_exclusions) && in_array($s, $this->_exclusions) ) {
			return $srcToken;
		} else {
			// Stem word
			$s = $this->_stemmer->stem( $srcToken->getTermText() );
			
			if( $s != null ) {
				$token = new Zend_Search_Lucene_Analysis_Token(
																	 $s,
																	 $srcToken->getStartOffset(),
																	 $srcToken->getEndOffset());

				$token->setPositionIncrement($srcToken->getPositionIncrement());

				return $token;
			}
		}

		// If nothing happened, returns original token.
		return $srcToken;
	}
}
