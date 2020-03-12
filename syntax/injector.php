<?php

if (!defined('DOKU_INC')) die();

include_once 'PlantUmlDiagram.php';

class syntax_plugin_plantumlparser_injector extends DokuWiki_Syntax_Plugin {
    private $TAG = 'uml';

    private const OUTPUT_FORMAT_SVG = 0;
    private const OUTPUT_FORMAT_PNG = 1;
    private const OUTPUT_FORMAT_TXT = 2;

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }
    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 199; // In case we are operating in a Dokuwiki that has the other PlantUML plugin we want to beat it.
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<'.$this->TAG.'>\n*.*?\n*</'.$this->TAG.'>',$mode,'plugin_plantumlparser_injector');
    }

    /**
     * Handle matches of the plantumlparser syntax
     *
     * @param string          $match   The match of the syntax
     * @param int             $state   The state of the handler
     * @param int             $pos     The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $markup        = str_replace('</' . $this->TAG . '>', '', str_replace('<' . $this->TAG . '>', '', $match));
        $diagramObject = new PlantUmlDiagram($markup);

        return [
            'id' => sha1($diagramObject->getSVGDiagramUrl()),
            'markup' => $diagramObject->getMarkup(),
            'data' => [
                 'svg' => strstr($diagramObject->getSVG(), "<svg"),
 		 'txt' => $diagramObject->getTXT(),
	    ], 
	    'url' => [
                'svg' => $diagramObject->getSVGDiagramUrl(),
                'png' => $diagramObject->getPNGDiagramUrl(),
                'txt' => $diagramObject->getTXTDiagramUrl(),
            ],
        ];
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode != 'xhtml') return false;

	/* get config */ 
	$output_format = syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_SVG;
	$show_links = 0;

	/* fallbacks for SVG rendering... */
	if($output_format == syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_SVG) {
		if(is_a($renderer,'renderer_plugin_dw2pdf') && preg_match("/(@startlatex|@startmath|<math|<latex)/", $data['markup'])) {
		        /* Fallback to PNG if latex should be rendered for a PDF */
			$output_format = syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_PNG;
		} else if(preg_match("/(@startditaa|ditaa\()/", $data['markup'])) {
		        /* Fallback to PNG due bug in plantuml ditaa to svg renderer */
			$output_format = syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_PNG;
		} else if(strlen($data['data']['svg']) == 0) {
			/* No SVG content returned from plant uml server */
			$output_format = syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_PNG;
		}
	}	

	/* debug out */
/*	$renderer->doc .= "<pre>" . $data['markup'] .  "</pre>";
	$renderer->doc .= "format: " . $output_format . "<br />";
	$renderer->doc .= "svg: " . strlen($data['data']['svg']) . "<br />";
	$renderer->doc .= "txt: " . strlen($data['data']['txt']) . "<br />"; */

	/* outer div */
        $renderer->doc .= "<div id='plant-uml-diagram-".$data['id']."'>";

	/* add diagram */
	if($output_format == syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_SVG) {
		$renderer->doc .= $data['data']['svg'];
	} else if($output_format == syntax_plugin_plantumlparser_injector::OUTPUT_FORMAT_PNG) {
		$renderer->doc .= "<img src='".$data['url']['png']."'>";	
	} else {
		$renderer->doc .= "<pre>" . $data['data']['txt'] . "</pre>";	
	}

	if($show_links) {
	/* add download links */
		$renderer->doc .= "<div id=\"plantumlparse_link_section\">";
        	$renderer->doc .= "<a target='_blank' href='".$data['url']['svg']."'>SVG</a> | ";
        	$renderer->doc .= "<a target='_blank' href='".$data['url']['png']."'>PNG</a> | ";
        	$renderer->doc .= "<a target='_blank' href='".$data['url']['txt']."'>TXT</a>";
        	$renderer->doc .= "</div>";
	}

        /* outer div */
	$renderer->doc .= "</div>";

        return true;
    }
}
