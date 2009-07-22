<?xml version='1.0'?>
<xsl:stylesheet 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	version="1.0"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
>

	<!-- DEBUG mode or not -->
	<xsl:param name="plib.debug" select="false()" />
	<!-- Output the main PLib description node? -->
	<xsl:param name="plib.description" select="true()" />
	<!-- Allow source view -->
	<xsl:param name="plib.source-view" />
	<!-- Just show this class -->
	<xsl:param name="plib.class" />
	<!-- Form var. Example file to render -->
	<xsl:param name="form.__plibexample" />
	<!-- Form var. Namespace to focus -->
	<xsl:param name="form.__plibnamespace" />
	<!-- Form.var. Module to focus -->
	<xsl:param name="form.__plibmodule" />
	<!-- Form.var. Class to focus -->
	<xsl:param name="form.__plibclass"  />
	<!-- Form.var. Function to focus -->
	<xsl:param name="form.__plibfunction" />
	<!-- Form.var. Show license -->
	<xsl:param name="form.__pliblicense" />
	<!-- Form.var. Show README -->
	<xsl:param name="form.__plibreadme" />
	<!-- Form.var. Show source -->
	<xsl:param name="form.__plibsource" />
	<!-- Form.var. Show CHANGELOG -->
	<xsl:param name="form.__plibchangelog" />
	<!-- Alternative source and example directory -->
	<xsl:param name="plib.alternative-source-path" select="false()" />

</xsl:stylesheet>