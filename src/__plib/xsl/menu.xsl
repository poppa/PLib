<?xml version='1.0'?>
<xsl:stylesheet
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	version="1.0"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
	exclude-result-prefixes="php xsl"
>

	<xsl:template name="menu">
		<xsl:param name="focus" />
		<xsl:apply-templates select="namespace" mode="menu">
			<xsl:with-param name="focus" select="$focus" />
		</xsl:apply-templates>
	</xsl:template>

	<xsl:template match="namespace" mode="menu">
		<xsl:param name="focus" />
		<xsl:variable name="name">
			<xsl:choose>
				<xsl:when test="@name = ''">PLib</xsl:when>
				<xsl:otherwise><xsl:value-of select="@name" /></xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		<ul>
			<li>
				<a>
					<xsl:attribute name="href">
						<xsl:value-of select="$server.php_self" />
						<xsl:if test="string-length(@name)">?__plibnamespace=<xsl:value-of select="@name" /></xsl:if>
					</xsl:attribute>
					<xsl:if test="$focus = @name">
						<xsl:attribute name="class">selected</xsl:attribute>
					</xsl:if>
					<span><xsl:value-of select="$name" /></span>
				</a>
				<xsl:apply-templates select="node()" mode="menu">
					<xsl:with-param name="focus" select="$focus" />
				</xsl:apply-templates>
			</li>
		</ul>
	</xsl:template>

	<xsl:template match="*" mode="menu"></xsl:template>

</xsl:stylesheet>