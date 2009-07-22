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

		THIS TEMPLATE ONLY DRAWS THE BODY PART OF THE DOC SITE

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
		<xsl:call-template name="draw-namespace" />
		<xsl:choose>
			<!-- Namespace -->
			<xsl:when test="$form.__plibnamespace">
				<xsl:apply-templates select="//namespace[@name = $form.__plibnamespace]"
				  mode="simpledoc">
					<xsl:with-param name="type" select="'namespace'" />
				</xsl:apply-templates>
			</xsl:when>

			<!-- Module -->
			<xsl:when test="$form.__plibmodule">
				<xsl:apply-templates select="//module[classpath = $form.__plibmodule]"
				  mode="simpledoc">
					<xsl:with-param name="type" select="'module'" />
				</xsl:apply-templates>
			</xsl:when>

			<!-- Class -->
			<xsl:when test="$form.__plibclass">
				<xsl:apply-templates select="//module[class/name = $form.__plibclass]"
				  mode="simpledoc">
					<xsl:with-param name="type" select="'module'" />
				</xsl:apply-templates>
			</xsl:when>

			<!-- Function -->
			<xsl:when test="$form.__plibfunction">
				<xsl:apply-templates select="//module[function/name = $form.__plibfunction]"
				  mode="simpledoc">
					<xsl:with-param name="type" select="'module'" />
				</xsl:apply-templates>
			</xsl:when>

			<!-- License -->
			<xsl:when test="$form.__pliblicense">
				<xsl:copy-of select="document(concat($plib.install-dir, '/__plib/info/LICENSE.html'))/div" />
			</xsl:when>

			<!-- Readme -->
			<xsl:when test="$form.__plibreadme">
				<xsl:copy-of select="document(concat($plib.install-dir, '/__plib/info/README.html'))/div" />
			</xsl:when>

			<!-- CHANGELOG -->
			<xsl:when test="$form.__plibchangelog">
				<xsl:copy-of select="document(concat($plib.install-dir, '/__plib/info/CHANGELOG.html'))/div" />
			</xsl:when>

			<!-- Default, i.e. start page -->
			<xsl:otherwise>
				<xsl:copy-of select="description/node()" />
				<xsl:call-template name="todolist" />
				<xsl:apply-templates select="//namespace[@name = '']"
				  mode="simpledoc">
					<xsl:with-param name="type" select="'namespace'" />
				</xsl:apply-templates>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<!-- Capture left overs -->
	<xsl:template match="*" />

</xsl:stylesheet>