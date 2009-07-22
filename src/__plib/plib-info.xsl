<?xml version='1.0'?>
<xsl:stylesheet
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  version="1.0"
  exclude-result-prefixes="php xsl"
>
  <xsl:import href="xsl/imports.xsl"/>
  
  <xsl:param name="plib.class" select="false()" />
  <xsl:param name="plib.function" select="false()" />

  <xsl:output
    media-type="text/html"
    method="xml"
    omit-xml-declaration="yes"
    indent="yes"
    standalone="yes"
    encoding="ISO-8859-1"
  />

  <xsl:template match="/plib">
    <style type="text/css">
    <xsl:call-template name="common-css" />
    BODY { text-align: left; padding: 5px }
    </style>
    <xsl:choose>
      <xsl:when test="$plib.class != false()">
      	<xsl:apply-templates select="//class[name = $plib.class]" mode="simpledoc"/>
      </xsl:when>
      <xsl:when test="$plib.function != false()">
	<xsl:apply-templates select="//function[name = $plib.function]" mode="simpledoc"/>
      </xsl:when>
      <xsl:otherwise>
	<div class="class-instance">
	  <h1>Classes</h1>
	  <ul>
	    <xsl:apply-templates select="//class[string-length(name) &gt; 0]" mode="infolist">
	      <xsl:sort select="name"/>
	    </xsl:apply-templates>
	  </ul>
	  <h1>Functions</h1>
	  <ul>
	    <xsl:apply-templates select="//function[string-length(name) &gt; 0]" mode="infolist">
	      <xsl:sort select="name"/>
	    </xsl:apply-templates>
	  </ul>
	</div>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Capture left overs -->
  <xsl:template match="*" />

</xsl:stylesheet>