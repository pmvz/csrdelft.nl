<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 5                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Stijn de Reede <sjr@gmx.co.uk>                               |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'BBCodeParser2/HTML/BBCodeParser2/Filter.php';

/**
* @package  HTML_BBCodeParser2
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*
*
* This is a parser to replace UBB style tags with their html equivalents. It
* does not simply do some regex calls, but is complete stack based
* parse engine. This ensures that all tags are properly nested, if not,
* extra tags are added to maintain the nesting. This parser should only produce
* xhtml 1.0 compliant code. All tags are validated and so are all their attributes.
* It should be easy to extend this parser with your own tags, see the _definedTags
* format description below.
*
*
* Usage:
* $parser = new HTML_BBCodeParser2($options = array(...));
* $parser->setText('normal [b]bold[/b] and normal again');
* $parser->parse();
* echo $parser->getParsed();
* or:
* $parser = new HTML_BBCodeParser2($options = array(...));
* echo $parser->qparse('normal [b]bold[/b] and normal again');
* or:
* echo HTML_BBCodeParser2::staticQparse('normal [b]bold[/b] and normal again');
*
*
* Setting the options from the ini file:
* $config = parse_ini_file('BBCodeParser.ini', true);
* $options = $config['HTML_BBCodeParser2'];
*
* The _definedTags variables should be in this format:
* array('tag'                                // the actual tag used
*           => array('htmlopen'  => 'open',  // the opening tag in html
*                    'htmlclose' => 'close', // the closing tag in html,
*                                               can be set to an empty string
*                                               if no closing tag is present
*                                               in html (like <img>)
*                    'allowed'   => 'allow', // tags that are allowed inside
*                                               this tag. Values can be all
*                                               or none, or either of these
*                                               two, followed by a ^ and then
*                                               followed by a comma seperated
*                                               list of exceptions on this
*                    'attributes' => array() // an associative array containing
*                                               the tag attributes and their
*                                               printf() html equivalents, to
*                                               which the first argument is
*                                               the value, and the second is
*                                               the quote. Default would be
*                                               something like this:
*                                               'attr' => 'attr=%2$s%1$s%2$s'
*                   ),
*       'etc'
*           => (...)
*       )
*/
class HTML_BBCodeParser2 {
    /**
     * An array of tags parsed by the engine, should be overwritten by filters
     *
     * @var      array
     */
	protected $_definedTags  = array();

    /**
     * A string containing the input
     *
     * @var      string
     */
	protected $_text          = '';

    /**
     * A string containing the preparsed input
     *
     * @var      string
     */
	protected $_preparsed     = '';

    /**
     * An array tags and texts build from the input text
     *
     * @var      array
     */
	private $_tagArray      = array();

    /**
     * A string containing the parsed version of the text
     *
     * @var      string
     */
    private $_parsed        = '';

    /**
     * An array of options, filled by an ini file or through the contructor
     *
     * @var      array
     */
    protected $_options = array(
        'quotestyle'    => 'double',
        'quotewhat'     => 'all',
        'open'          => '[',
        'close'         => ']',
        'xmlclose'      => true,
        'filters'       => 'Basic',
		'allowhtml'		=> false
    );

    /**
     * An array of filters used for parsing
     *
     * @var      HTML_BBCodeParser2_Filter[]
     */
    private $_filters       = array();

	/**
	 * List of plugins with open tags, which will modify the unmatched text between their tags
	 *
	 * @var array
	 */
	private $_pluginsModifyingText = array();

	/**
	 * string pluginname that disabled html output or null if html output is enabled
	 *
	 * @var bool|string
	 */
	private $_outputDisabledBy = null;

	/**
	 * Constructor, initialises the options and filters
	 *
	 * Sets options to properly escape the tag
	 * characters in preg_replace() etc.
	 *
	 * All the filters in the options are initialised and their defined tags
	 * are copied into the private variable _definedTags.
	 *
	 * @param    array $options to use, can be left out
	 *
	 * @author   Stijn de Reede  <sjr@gmx.co.uk>
	 */
    public function __construct($options = array()) {
        // set the options passed as an argument
        foreach ($options as $k => $v )  {
            $this->_options[$k] = $v;
        }

        // add escape open and close chars to the options for preg escaping
        $preg_escape = '\^$.[]|()?*+{}';
        if ($this->_options['open'] != '' && strpos($preg_escape, $this->_options['open'])) {
            $this->_options['open_esc'] = "\\".$this->_options['open'];
        } else {
            $this->_options['open_esc'] = $this->_options['open'];
        }
        if ($this->_options['close'] != '' && strpos($preg_escape, $this->_options['close'])) {
            $this->_options['close_esc'] = "\\".$this->_options['close'];
        } else {
            $this->_options['close_esc'] = $this->_options['close'];
        }

        // set the options back so that child classes can use them */
        $baseoptions = $this->_options;
        unset($baseoptions);

        // return if this is a subclass
        if (is_subclass_of($this, 'HTML_BBCodeParser2_Filter')) {
            return;
        }

        // extract the definedTags from subclasses */
        $this->addFilters($this->_options['filters']);
    }

	/**
	 * Option setter
	 *
	 * @param string $name of option
	 * @param mixed $value of option
	 *
	 * @author Lorenzo Alberton <l.alberton@quipo.it>
	 */
    function setOption($name, $value) {
        $this->_options[$name] = $value;
    }

	/**
	 * Add a new filter
	 *
	 * @param string $filter
	 * @throws InvalidArgumentException
	 *
	 * @author Lorenzo Alberton <l.alberton@quipo.it>
	 */
    public function addFilter($filter) {
        $filter = ucfirst($filter);
        if (!array_key_exists($filter, $this->_filters)) {
            $class = 'HTML_BBCodeParser2_Filter_'.$filter;
            @include_once 'BBCodeParser2/HTML/BBCodeParser2/Filter/'.$filter.'.php';
            if (!class_exists($class)) {
                throw new InvalidArgumentException("Failed to load filter $filter");
            }
            $this->_filters[$filter] = new $class;
            $this->_definedTags = array_merge(
                $this->_definedTags,
                $this->_filters[$filter]->_definedTags
            );
        }
    }

    /**
     * Remove an existing filter
     *
     * @param string $filter
	 *
     * @author Lorenzo Alberton <l.alberton@quipo.it>
     */
    public function removeFilter($filter) {
        $filter = ucfirst(trim($filter));
        if (!empty($filter) && array_key_exists($filter, $this->_filters)) {
            unset($this->_filters[$filter]);
        }
        // also remove the related $this->_definedTags for this filter,
        // preserving the others
        $this->_definedTags = array();
        foreach (array_keys($this->_filters) as $filter) {
            $this->_definedTags = array_merge(
                $this->_definedTags,
                $this->_filters[$filter]->_definedTags
            );
        }
    }

    /**
     * Add new filters
     *
     * @param array|string $filters
     * @return boolean true if all ok, false if not.
	 *
     * @author Lorenzo Alberton <l.alberton@quipo.it>
     */
    public function addFilters($filters) {
        if (is_string($filters)) {
            //comma-separated list
            if (strpos($filters, ',') !== false) {
                $filters = explode(',', $filters);
            } else {
                $filters = array($filters);
            }
        }
        if (!is_array($filters)) {
            //invalid format
            return false;
        }
        foreach ($filters as $filter) {
            if (trim($filter)){
                $this->addFilter($filter);
            }
        }
        return true;
    }

    /**
     * Executes statements before the actual array building starts
     *
     * This method should be overwritten in a filter if you want to do
     * something before the parsing process starts. This can be useful to
     * allow certain short alternative tags which then can be converted into
     * proper tags with preg_replace() calls.
     * The main class walks through all the filters and and calls this
     * method. The filters should modify their private $_preparsed
     * variable, with input from $_text.
     *
     * @see      $_text
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    protected function _preparse() {
        // default: assign _text to _preparsed, to be overwritten by filters
        $this->_preparsed = $this->_text;

        // return if this is a subclass
        if (is_subclass_of($this, 'HTML_BBCodeParser2')) {
            return;
        }

        // walk through the filters and execute _preparse
        foreach ($this->_filters as $filter) {
            $filter->setText($this->_preparsed);
            $filter->_preparse();
            $this->_preparsed = $filter->getPreparsed();
        }
    }

    /**
     * Builds the tag array from the input string $_text
     *
     * An array consisting of tag and text elements is contructed from the
     * $_preparsed variable. The method uses _buildTag() to check if a tag is
     * valid and to build the actual tag to be added to the tag array.
     *
     * TODO: - rewrite whole method, as this one is old and probably slow
     *       - see if a recursive method would be better than an iterative one
     *
     * @see      _buildTag()
     * @see      $_text
     * @see      $_tagArray
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    private function _buildTagArray() {
        $this->_tagArray = array();
        $str = $this->_preparsed;
        $strPos = 0;
        $strLength = strlen($str);

        while (($strPos < $strLength)) {
            $tag = array();
            $openPos = strpos($str, $this->_options['open'], $strPos);
            if ($openPos === false) {
                $openPos = $strLength;
            }
            if ($openPos + 1 > $strLength) {
                $nextOpenPos = $strLength;
            } else {
                $nextOpenPos = strpos($str, $this->_options['open'], $openPos + 1);
                if ($nextOpenPos === false) {
                    $nextOpenPos = $strLength;
                }
            }
            $closePos = strpos($str, $this->_options['close'], $strPos);
            if ($closePos === false) {
                $closePos = $strLength + 1;
            }

            if ($openPos == $strPos) {
                if (($nextOpenPos < $closePos)) {
                    // new open tag before closing tag: treat as text
                    $newPos = $nextOpenPos;
                    $tag['text'] = substr($str, $strPos, $nextOpenPos - $strPos);
                    $tag['type'] = 0;
                } else {
                    // possible valid tag
                    $newPos = $closePos + 1;
                    $newTag = $this->_buildTag(substr($str, $strPos, $closePos - $strPos + 1));
                    if (($newTag !== false)) {
                        $tag = $newTag;
                    } else {
                        // no valid tag after all
                        $tag['text'] = substr($str, $strPos, $closePos - $strPos + 1);
                        $tag['type'] = 0;
                    }
                }
            } else {
                // just text
                $newPos = $openPos;
                $tag['text'] = substr($str, $strPos, $openPos - $strPos);
                $tag['type'] = 0;
            }

            // join 2 following text elements
            if ($tag['type'] === 0 && isset($prev) && $prev['type'] === 0) {
                $tag['text'] = $prev['text'].$tag['text'];
                array_pop($this->_tagArray);
            }

            $this->_tagArray[] = $tag;
            $prev = $tag;
            $strPos = $newPos;
        }
    }

    /**
     * Builds a tag from the input string
     *
     * This method builds a tag array based on the string it got as an
     * argument. If the tag is invalid, <false> is returned. The tag
     * attributes are extracted from the string and stored in the tag
     * array as an associative array.
     *
     * @param    string          string to build tag from
     * @return   array           tag in array format
     * @see      _buildTagArray()
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    private function _buildTag($str) {
        $tag = array('text' => $str, 'attributes' => array());

        if (substr($str, 1, 1) == '/') {        // closing tag

            $tag['tag'] = strtolower(substr($str, 2, strlen($str) - 3));
            if (!in_array($tag['tag'], array_keys($this->_definedTags))) {
                return false;                   // nope, it's not valid
            } else {
                $tag['type'] = 2;
                return $tag;
            }
        } else {                                // opening tag

            $tag['type'] = 1;
            if (strpos($str, ' ') && (strpos($str, '=') === false)) {
                return false;                   // nope, it's not valid
            }

            // tnx to Onno for the regex
            // split the tag with arguments and all
            $oe = $this->_options['open_esc'];
            $ce = $this->_options['close_esc'];
            $tagArray = array();
            if (preg_match("!$oe([a-z0-9]+)[^$ce]*$ce!i", $str, $tagArray) == 0) {
                return false;
            }
            $tag['tag'] = strtolower($tagArray[1]);
            if (!in_array($tag['tag'], array_keys($this->_definedTags))) {
                return false;                   // nope, it's not valid
            }

            // tnx to Onno for the regex
            // validate the arguments
            $attributeArray = array();
            $regex = "![\s$oe]([a-z0-9]+)=(\"[^\s$ce]+\"|";
            if ($tag['tag'] != 'url') {
                $regex .= "[^\s$ce][^=$ce]*";
            } else {
				$regex .= "[^\s$ce]+";
			}
            $regex .= ")(?=[\s$ce])!i";

            preg_match_all($regex, $str, $attributeArray, PREG_SET_ORDER);
            foreach ($attributeArray as $attribute) {
                $attNam = strtolower($attribute[1]);
                if (in_array($attNam, array_keys($this->_definedTags[$tag['tag']]['attributes']))) {
                    if ($attribute[2][0] == '"' && $attribute[2][strlen($attribute[2])-1] == '"') {
                        $tag['attributes'][$attNam] = substr($attribute[2], 1, -1);
                    } else {
                        $tag['attributes'][$attNam] = $attribute[2];
                    }
                }
            }
            return $tag;
        }
    }

    /**
     * Validates the tag array, regarding the allowed tags
     *
     * While looping through the tag array, two following text tags are
     * joined, and it is checked that the tag is allowed inside the
     * last opened tag.
     * By remembering what tags have been opened it is checked that
     * there is correct (xml compliant) nesting.
     * In the end all still opened tags are closed.
     *
     * @see      _isAllowed()
     * @see      $_tagArray
     * @author   Stijn de Reede  <sjr@gmx.co.uk>, Seth Price <seth@pricepages.org>
     */
    private function _validateTagArray() {
        $newTagArray = array();
        $openTags = array();
        foreach ($this->_tagArray as $tag) {
            $prevTag = end($newTagArray);
            switch ($tag['type']) {
            case 0:
                if (($child = $this->_childNeeded(end($openTags), 'text')) &&
                    $child !== false &&
                    /*
                     * No idea what to do in this case: A child is needed, but
                     * no valid one is returned. We'll ignore it here and live
                     * with it until someone reports a valid bug.
                     */
                    $child !== true )
                {
                    if (trim($tag['text']) == '') {
                        //just an empty indentation or newline without value?
                        continue;
                    }
                    $newTagArray[] = $child;
                    $openTags[] = $child['tag'];
                }
                if ($prevTag['type'] === 0) {
                    $tag['text'] = $prevTag['text'].$tag['text'];
                    array_pop($newTagArray);
                }
                $newTagArray[] = $tag;
                break;

            case 1:
                if (!$this->_isAllowed(end($openTags), $tag['tag']) ||
                   ($parent = $this->_parentNeeded(end($openTags), $tag['tag'])) === true ||
                   ($child  = $this->_childNeeded(end($openTags),  $tag['tag'])) === true) {
                    $tag['type'] = 0;
                    if ($prevTag['type'] === 0) {
                        $tag['text'] = $prevTag['text'].$tag['text'];
                        array_pop($newTagArray);
                    }
                } else {
                    if ($parent) {
                        /*
                         * Avoid use of parent if we can help it. If we are
                         * trying to insert a new parent, but the current tag is
                         * the same as the previous tag, then assume that the
                         * previous tag structure is valid, and add this tag as
                         * a sibling. To add as a sibling, we need to close the
                         * current tag.
                         */
                        if ($tag['tag'] == end($openTags)){
                            $newTagArray[] = $this->_buildTag('[/'.$tag['tag'].']');
                            array_pop($openTags);
                        } else {
                            $newTagArray[] = $parent;
                            $openTags[] = $parent['tag'];
                        }
                    }
                    if ($child) {
                        $newTagArray[] = $child;
                        $openTags[] = $child['tag'];
                    }
                    $openTags[] = $tag['tag'];
                }
                $newTagArray[] = $tag;
                break;

            case 2:
                if (($tag['tag'] == end($openTags) || $this->_isAllowed(end($openTags), $tag['tag']))) {
                    if (in_array($tag['tag'], $openTags)) {
                        $tmpOpenTags = array();
                        while (end($openTags) != $tag['tag']) {
                            $newTagArray[] = $this->_buildTag('[/'.end($openTags).']');
                            $tmpOpenTags[] = end($openTags);
                            array_pop($openTags);
                        }
                        $newTagArray[] = $tag;
                        array_pop($openTags);
                        /* why is this here? it just seems to break things
                         * (nested lists where closing tags need to be
                         * generated)
                        while (end($tmpOpenTags)) {
                            $tmpTag = $this->_buildTag('['.end($tmpOpenTags).']');
                            $newTagArray[] = $tmpTag;
                            $openTags[] = $tmpTag['tag'];
                            array_pop($tmpOpenTags);
                        }*/
                    }
                } else {
                    $tag['type'] = 0;
                    if ($prevTag['type'] === 0) {
                        $tag['text'] = $prevTag['text'].$tag['text'];
                        array_pop($newTagArray);
                    }
                    $newTagArray[] = $tag;
                }
                break;
            }
        }
        while (end($openTags)) {
            $newTagArray[] = $this->_buildTag('[/'.end($openTags).']');
            array_pop($openTags);
        }
        $this->_tagArray = $newTagArray;
    }

    /**
     * Checks to see if a parent is needed
     *
     * Checks to see if the current $in tag has an appropriate parent. If it
     * does, then it returns false. If a parent is needed, then it returns the
     * first tag in the list to add to the stack.
     *
     * @param    string  $out    tag that is on the outside
     * @param    string  $in     tag that is on the inside
     * @return   boolean         false if not needed, tag if needed, true if out
     *                           of  our minds
     * @see      _validateTagArray()
     * @author   Seth Price <seth@pricepages.org>
     */
    private function _parentNeeded($out, $in) {
        if (!isset($this->_definedTags[$in]['parent']) ||
            ($this->_definedTags[$in]['parent'] == 'all')
        ) {
            return false;
        }

        $ar = explode('^', $this->_definedTags[$in]['parent']);
        $tags = explode(',', $ar[1]);
        if ($ar[0] == 'none'){
            if ($out && in_array($out, $tags)) {
                return false;
            }
            //Create a tag from the first one on the list
            return $this->_buildTag('['.$tags[0].']');
        }
        if ($ar[0] == 'all' && $out && !in_array($out, $tags)) {
            return false;
        }
        // Tag is needed, we don't know which one. We could make something up,
        // but it would be so random, I think that it would be worthless.
        return true;
    }

    /**
     * Checks to see if a child is needed
     *
     * Checks to see if the current $out tag has an appropriate child. If it
     * does, then it returns false. If a child is needed, then it returns the
     * first tag in the list to add to the stack.
     *
     * @param    string  $out    tag that is on the outside
     * @param    string  $in     tag that is on the inside
     * @return   boolean         false if not needed, tag if needed, true if out
     *                           of our minds
	 *
     * @see      _validateTagArray()
     * @author   Seth Price <seth@pricepages.org>
     */
    private function _childNeeded($out, $in) {
        if (!isset($this->_definedTags[$out]['child']) ||
           ($this->_definedTags[$out]['child'] == 'all')
        ) {
            return false;
        }

        $ar = explode('^', $this->_definedTags[$out]['child']);
        $tags = explode(',', $ar[1]);
        if ($ar[0] == 'none'){
            if ($in && in_array($in, $tags)) {
                return false;
            }
            //Create a tag from the first one on the list
            return $this->_buildTag('['.$tags[0].']');
        }
        if ($ar[0] == 'all' && $in && !in_array($in, $tags)) {
            return false;
        }
        // Tag is needed, we don't know which one. We could make something up,
        // but it would be so random, I think that it would be worthless.
        return true;
    }

    /**
     * Checks to see if a tag is allowed inside another tag
     *
     * The allowed tags are extracted from the private _definedTags array.
     *
     * @param    string  $out    tag that is on the outside
     * @param    string  $in     tag that is on the inside
     * @return   boolean         return true if the tag is allowed, false
     *                           otherwise
     *
     * @see      _validateTagArray()
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    private function _isAllowed($out, $in) {
        if (!$out || ($this->_definedTags[$out]['allowed'] == 'all')) {
            return true;
        }
        if ($this->_definedTags[$out]['allowed'] == 'none') {
            return false;
        }

        $ar = explode('^', $this->_definedTags[$out]['allowed']);
        $tags = explode(',', $ar[1]);
        if ($ar[0] == 'none' && in_array($in, $tags)) {
            return true;
        }
        if ($ar[0] == 'all'  && in_array($in, $tags)) {
            return false;
        }
        return false;
    }

    /**
     * Builds a parsed string based on the tag array
     *
     * The correct html and attribute values are extracted from the private
     * _definedTags array.
     *
     * @see      $_tagArray
     * @see      $_parsed
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
	private function _buildParsedString() {
        $this->_parsed = '';
        foreach ($this->_tagArray as $tag) {

			if(!$this->isOutputEnabled($tag)) {
				//html output is disabled by a plugin
				continue;
			}

			switch ($tag['type']) {

            // just text
            case 0:
				// $tag = array(
				//     'text' => 'unmatched text'
				//     'type' => 1
			    // )

				//if only bbcode is allowed, escape html and replace newlines
				if(!$this->isHtmlAllowed()) {
					$tag['text'] = htmlspecialchars($tag['text']);

					$tag['text'] = nl2br($tag['text'], $this->_options['xmlclose']);
				}

				//plugins which modify the unmatched text
				foreach($this->_pluginsModifyingText as $tagname) {
					$tag['tag'] = $tagname;
					$tag['text'] = $this->renderPlugin($tag, $enable);
				}

				$this->_parsed .= $tag['text'];
                break;

            // opening tag
            case 1:
				// $tag = array(
				//     'text' => '[tag=etc etc]'
				//     'type' => 1
				//     'tag'  => 'tag'
				//     'attributes' => Array(
				//		    'key' => 'value'
				//		))

				$definedTag = $this->_definedTags[$tag['tag']];

				//let plugin parse
				if(isset($definedTag['plugin'])) {
					$enabled = false;
					$this->_parsed .= $this->renderPlugin($tag, $enabled);

					//start modifying unmatched text by this plugin
					if($enabled === true) {
						$this->addTextModifyingPlugin($tag['tag']);
					}
					//disable output between these tags
					if($enabled === 'disable_output') {
						$this->disableOutput($tag['tag']);
					}
					continue;
				}

				$this->_parsed .= '<'.$definedTag['htmlopen'];
				$this->_parsed .= $this->buildAttributes($tag);
				if(isset($definedTag['htmlopen_postfix'])) {
					$this->_parsed .= $definedTag['htmlopen_postfix'];
				}
                if ($definedTag['htmlclose'] == '' && $this->_options['xmlclose']) {
                    $this->_parsed .= ' /';
                }
                $this->_parsed .= '>';
                break;

            // closing tag
            case 2:
				// $tag = array(
				//     'text' => [/tag]
    			//     'type' => 2
				//     'tag' => 'tag'
    			//     'attributes' => array()
				// )

				//let plugin parse
				if(isset($this->_definedTags[$tag['tag']]['plugin'])) {
					$enabled = true;
					$this->_parsed .= $this->renderPlugin($tag, $enabled);

					//finish modifying unmatched text by this plugin
					if ($enabled === false) {
						$this->removeTextModifyingPlugin($tag['tag']);
					}
					//enable output again after this close tag
					if($enabled === 'enable_output') {
						$this->enableOutput($tag['tag']);
					}
					continue;
				}

                if ($this->_definedTags[$tag['tag']]['htmlclose'] != '') {
                    $this->_parsed .= '</'.$this->_definedTags[$tag['tag']]['htmlclose'].'>';
                }
                break;
            }
		}
	}

	/**
	 * Build attributes included escaping
	 *
	 * @param array $tag
	 * @return string
	 */
	private function buildAttributes($tag) {
		//quote style
		$q = '"';
		if ($this->_options['quotestyle'] == 'single') $q = "'";
		if ($this->_options['quotestyle'] == 'double') $q = '"';

		$str = '';
		foreach ($tag['attributes'] as $a => $v) {
			//prevent XSS attacks. IMHO this is not enough, though...
			//@see http://pear.php.net/bugs/bug.php?id=5609
			$v = preg_replace('#(script|about|applet|activex|chrome):#is', "\\1&#058;", $v);
			$v = htmlspecialchars($v);
			$v = str_replace('&amp;amp;', '&amp;', $v);

			$usequotes = ($this->_options['quotewhat'] == 'nothing') ||
						 (($this->_options['quotewhat'] == 'strings') && is_numeric($v));
			if ($usequotes) {
				$str .= sprintf($this->_definedTags[$tag['tag']]['attributes'][$a], $v, '');
			} else {
				$str .= sprintf($this->_definedTags[$tag['tag']]['attributes'][$a], $v, $q);
			}
		}
		return $str;
	}

    /**
     * Sets text in the object to be parsed
     *
     * @param    string $str         the text to set in the object
	 *
     * @see      getText()
     * @see      $_text
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function setText($str) {
        $this->_text = $str;
    }

    /**
     * Gets the unparsed text from the object
     *
     * @return   string          the text set in the object
	 *
     * @see      setText()
     * @see      $_text
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function getText() {
        return $this->_text;
    }

    /**
     * Gets the preparsed text from the object
     *
     * @return   string          the text set in the object
	 *
	 * @see      _preparse()
     * @see      $_preparsed
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function getPreparsed() {
        return $this->_preparsed;
    }

    /**
     * Gets the parsed text from the object
     *
     * @return   string          the parsed text set in the object
	 *
     * @see      parse()
     * @see      $_parsed
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function getParsed() {
        return $this->_parsed;
    }

    /**
     * Parses the text set in the object
     *
     * @see      _preparse()
     * @see      _buildTagArray()
     * @see      _validateTagArray()
     * @see      _buildParsedString()
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function parse() {
        $this->_preparse();
        $this->_buildTagArray();
        $this->_validateTagArray();
        $this->_buildParsedString();
    }

    /**
     * Quick method to do setText(), parse() and getParsed at once
     *
	 * @param    string $str
     * @return   string
	 *
     * @see      parse()
     * @see      $_text
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function qparse($str) {
        $this->_text = $str;
        $this->parse();
        return $this->_parsed;
    }

    /**
     * Quick static method to do setText(), parse() and getParsed at once
     *
	 * @param    string $str
     * @return   string
	 *
     * @see      parse()
     * @see      $_text
     * @author   Stijn de Reede  <sjr@gmx.co.uk>
     */
    public function staticQparse($str) {
        $p = new HTML_BBCodeParser2();
        $str = $p->qparse($str);
        unset($p);
        return $str;
    }

	/**
	 * Calls the html build method of the right plugin
	 *
	 * @param array $tag
	 * @param bool  $enabled (reference) type 1 and 2: if plugin should be called on unmatched text between tags
	 *                                   type 0:       if htmlspecialchars is enabled
	 * @return array
	 */
	private function renderPlugin($tag, &$enabled) {
		$method = 'html_' . $tag['tag'];
		$filterobjectname = $this->_definedTags[$tag['tag']]['plugin'];

		//defaults
		if($tag['type'] == 0) {
			$str = $tag['text']; //unmatched text
		} else {
			$str = ''; //no tag
		}

		$return = $this->_filters[$filterobjectname]->$method($tag, $enabled);
		if ($return !== false) {
			$str = $return;
		}
		return $str;
	}


	/**
	 * Add plugin to list of plugins that will modify unmatched text
	 *
	 * @param string $tagname
	 */
	private function addTextModifyingPlugin($tagname) {
		$this->_pluginsModifyingText[] = $tagname;
	}

	/**
	 * Remove plugin from list of plugins that will modify unmatched text
	 *
	 * @param string $tagname
	 */
	private function removeTextModifyingPlugin($tagname) {
		$key = array_search($tagname, $this->_pluginsModifyingText);
		if (false !== $key) {
			unset($this->_pluginsModifyingText[$key]);
		}
	}

	/**
	 * @param string $tag
	 * @throws Exception
	 */
	private function disableOutput($tag) {
		if($this->_outputDisabledBy !== null) {
			throw new Exception('Html Output is al uitgeschakeld door een andere plugin');
		}
		$this->_outputDisabledBy = $tag;
	}

	/**
	 * @param string $tag
	 * @throws Exception
	 */
	private function enableOutput($tag) {
		if($tag !== $this->_outputDisabledBy) {
			throw new Exception('Html Output is uitgeschakeld door een andere plugin');
		}
		$this->_outputDisabledBy = null;
	}

	/**
	 * @param $tag
	 * @return bool
	 */
	private function isOutputEnabled($tag)	{
		//if enabled no pluginname is set
		if($this->_outputDisabledBy === null) {
			return true;
		}

		//unmatched text has no name, so disable
		if(!isset($tag['tag'])) {
			return false;
		}

		//only the blocking plugin may pass
		if($this->_outputDisabledBy == $tag['tag']) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Is Html allowed at the moment (during html building)
	 * @see _buildParsedString
	 *
	 * @return bool
	 */
	private function isHtmlAllowed() {
		//allowed by general option
		if($this->_options['allowhtml']) {
			return true;
		}

		//are we between [html]..[/html] tags
		return in_array('html', $this->_pluginsModifyingText);
	}


}
