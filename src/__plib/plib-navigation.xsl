<?xml version='1.0'?>
<xsl:stylesheet
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	version="1.0"
	exclude-result-prefixes="php xsl"
>

	<!--
	==============================================================================

		THIS TEMPLATE ONLY DRAWS THE NAVIGATION MENU OF THE DOC SITE

	===========================================================================-->

	<xsl:import href="xsl/imports.xsl"/>

	<xsl:output
		media-type="text/xml+html"
	  method="xml"
		omit-xml-declaration="yes"
		indent="yes"
		standalone="no"
	/>

	<xsl:template match="/plib">
		<xsl:call-template name="menu">
			<xsl:with-param name="focus">
				<xsl:call-template name="find-namespace" />
			</xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<!-- Capture left overs -->
	<xsl:template match="*" />

</xsl:stylesheet>