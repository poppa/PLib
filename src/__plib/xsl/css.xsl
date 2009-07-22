<?xml version='1.0'?>
<xsl:stylesheet
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	version="1.0"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
	exclude-result-prefixes="php xsl"
>

	<xsl:template name="common-css">
		<![CDATA[
		BODY { font: 100%/140% Georgia, serif; text-align: center; margin: 0; padding: 0 }
		A { color: #036; }
		A:visited { color: #660; }
		H2 SMALL { font-weight: normal; }

		/* ----------------------------------*/

		UL.files { list-style: none; padding: 0; margin-left: 0; }
		UL.files LI { clear: left; margin-bottom: 15px; }
		UL.files IMG { float: left; margin-top: -2px; padding-right: 5px; }
		UL.files A { font-size: .9em; color: #622; text-decoration: none; line-height: 80%}
		UL.files A STRONG { display: block; }
		UL.files A .meta { color: #566; font-size: .9em;}

		H2, H3 { color: #622; }
		HR { border: none; background: #AAA; color: #AAA; height: 1px; }
		BLOCKQUOTE {
			font-style: italic;
			font-size: .9em;
			color: #422;
			}
		DIV.example {
			background: #EEFFEE;
			padding: 10px;
			border: 1px solid #AAA;
			}
		DIV.highlight DIV.header {
			padding: 5px 8px;
			background:  #FFECCF;
			border: 1px solid #FFECCF;
			border-top: 2px solid #E2BE88;
			border-bottom: 1px solid #E2BE88;
			font-style: italic;
			font-size: .9em;
			}
		DIV.highlight DIV.header .em { font-weight: bold; }
		DIV.highlight DIV.body {
			overflow: auto;
			position: relative;
			padding: 0px;
			background: #FFFFEF;
			}
		DIV.highlight DIV.body OL {
			margin: 0;
			background: #FFECCF;
			border: 1px solid #FFECCF;
			border-left: none;
			font-family: "Bitstream Vera Sans Mono", monospace, type-writer;
			font-size: .75em;
			padding-left: 50px;
			width: auto;
			}
		DIV.highlight DIV.body OL LI {
			background: #FFFFF4; padding-left: 10px; margin-bottom: 1px;
			}
		.clear { clear: both; }
		code, DIV.code PRE {
			font-family: "Bitstream Vera Sans Mono", monospace, type-writer;
			font-size: .75em;
			}
		DIV.code {
			border: 1px dotted #CCC;
			padding: 8px;
			background: #FFFFF4;
			/*font-size: 1.3em !important;*/
			line-height: 110%;
			}

		/* ----------------------------------------------- */

		H4 { margin-bottom: 5px; }
		H4.hidden { font-style: hidden; color: #900 }
		DIV.class-instance {
			border: 1px dotted #CCC;
			margin-bottom: 5px;
			padding: 10px;
			background: #EED;
			}

		DIV.class-instance H4 { margin: 0; font-size: 1.1em;}

		DIV.docblock {
			border: 1px dotted #CCC;
			padding: 8px;
			background: #F8F8F8;
			margin-bottom: 4px;
			}

		DIV.docblock H3 { margin-top: 8px; }
		DIV.docblock H3 SMALL { font-weight: bold; font-size: .6em; }
		DIV.docblock UL { color: #444; }
		DIV.docblock LI.param STRONG { font-style: italic; }
		DIV.docblock DIV.param-description {
			font-size: .9em;
			color: #222;
			font-style: italic;
			}
		DIV.param-description UL { margin-top: 0px; margin-bottom: 14px; }
		DIV.param-description P {
			margin-top: -2px;
		}

		DL.methods { margin-left: 15px; }
		DL.methods DT.inherited { color: #555; display: none }
		DL.methods DT.hidden { color: #900; font-style: italic }

		SPAN.modifier { color: #039 }
		SPAN.instance-name { font-weight: bold; }
		SPAN.return-type, SPAN.return-type A { color: #555; font-style: italic }

		DIV.namespace {
			border: 1px dotted #CCC;
			padding: 1px;
			margin: 0 0 10px 0;
			}

		DIV.namespace H2 {
			margin: 0; padding: 10px;
			background: #322;
			color: #EEE;
			}

		DIV.namespace H2 A { color: #CCA }

		DIV.instance-header {
			padding: 1px;
			border: 1px dotted #CCC;
			margin: 10px 0 5px 0;
			}
		DIV.instance-header P {
			margin: 0; padding: 8px;
			font-weight: bold;
			color: #EEE;
			background: #322
			}
		DIV.inherited-methods {
			padding: 10px; border: 1px dotted #CCC;
			margin-bottom: 5px;
			background: #F8F8F8;
			}
		DIV.inherited-methods P.header {
			text-transform: uppercase;
			font-weight: bold;
			margin: 10px 0 0 0;
			}
		DIV.inherited-methods EM { color: #444; }

		DIV.summary UL { font-size: .75em; margin-top: -10px; }
		
		.dim { color: #855; }
		
		SPAN.param { font-weight: normal; }

		H2.todo { margin: 0; font-size: 1.2em;}
		DIV#todo {
			padding: 10px 15px;
			border: 1px dotted #CCC;
			background: #FFFFEF;
			}
		DL.todo {}
		DL.todo DT { font-weight: bold; }
		DL.todo DD { margin-bottom: 10px; }
		
		/* ------------------------------------------------ */

		#toc2 { padding-left: 5px; }
		#summary {
			float: right;
			background: white;
			border: 1px solid #CCC;
			padding: 5px;
			}
		#plibinfo { padding: 10px; border: 1px dotted #CCC; }
		#plibinfo H2 { padding: 5px; margin-bottom: 0; }
		#plibinfo H3 { padding: 5px; margin-top: 0; }
		#plibinfo PRE {
			font-family: "andale mono", "courier new", type-writer;
			font-size: .75em;
			border: 1px solid #CCC;
			background: #FFFFEE;
			padding: 5px;
			line-height: 135%;
			}

		]]>
	</xsl:template>
	
	<xsl:template name="page-layout-css">
		<![CDATA[
		#header {
			padding: 30px 0;
			background: #322;
			color: #CCC;
			border-bottom: 5px solid black;
			margin-bottom: 20px;
			}
		#header A { color: #CCA; text-decoration: none; }
		#header A:hover { text-decoration: underline; }
		#header H1 { margin: 0 0 5px 0; }
		#header DIV A { font-size: .9em; }
		#wrapper, .base-align {
			width: 55em;
			margin: 0 auto;
			text-align: left;
			max-width: 95%;
			}
		#menu { width: 25%; float: left;  font-size: 90%; }
		#menu H1 { font-size: 1em; }
		#menu UL { list-style: none; padding: 0; margin-left: 0px }
		#menu UL A { text-decoration: none; color: #322; }
		#menu UL A.selected { text-decoration: underline; font-weight: bold; }
		#menu UL A:visited { color: #555; }
		#menu UL UL { padding-left: 15px; }
		#content { float: right; width: 73%; }
		#footer {
			margin-top: 20px;
			border-top: 1px dotted #322;
			color: #544;
			}
		#footer .content { font-size: 80%; }
		#footer A { color: #322; }
		]]>
	</xsl:template>
</xsl:stylesheet>