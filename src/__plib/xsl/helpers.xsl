<?xml version='1.0'?>
<xsl:stylesheet 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	version="1.0"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
>

	<xsl:template match="* | processing-instruction()">
		<xsl:copy>
			<xsl:copy-of select="@*" />
			<xsl:apply-templates/>
		</xsl:copy>
	</xsl:template>

</xsl:stylesheet>