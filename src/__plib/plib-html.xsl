<?xml version='1.0'?>
<xsl:stylesheet
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	version="1.0"
	exclude-result-prefixes="php xsl"
>
	<xsl:import href="xsl/imports.xsl"/>

	<xsl:output
		media-type="text/html"
	  method="xml"
		omit-xml-declaration="yes"
		indent="yes"
		standalone="yes"
		encoding="iso-8859-1"
		doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
		doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"
	/>

	<xsl:template match="/plib">
		<html xml:lang="en" lang="en">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
				<title>PLib simple documentation</title>
				<xsl:call-template name="mootools"/>
				<script type="text/javascript"><xsl:call-template name="common-js"/></script>
				<style type="text/css">
				<xsl:call-template name="common-css" />
				<xsl:call-template name="page-layout-css" />
				</style>
				<link rel="alternate" type="application/rss+xml" title="Changelog" href="http://plib.poppa.se/rss.php" />
			</head>
			<body id="top">
				<div id="header">
					<div class="base-align">
						<h1><a href="{$server.php_self}">PLibDoc</a></h1>
						<div>
							<a href="{$server.php_self}?__pliblicense=1">License</a>
							&#x2022;
							<a href="{$server.php_self}?__plibreadme=1">Readme</a>
							&#x2022;
							<a href="{$server.php_self}?__plibchangelog=1">Changelog</a>
						</div>
					</div>
				</div>
				<div id="wrapper">
					<div id="menu">
						<h1>Namespaces</h1>
						<xsl:call-template name="menu">
							<xsl:with-param name="focus">
								<xsl:call-template name="find-namespace" />
							</xsl:with-param>
						</xsl:call-template>
					</div>
					<div id="content">
						<xsl:call-template name="run" />
					</div>
				</div>
			</body>
		</html>
	</xsl:template>

	<xsl:template name="run">
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
				<xsl:value-of select="description/node()" disable-output-escaping="yes"/>
				<xsl:call-template name="todolist" />
				<xsl:apply-templates select="//namespace[@name = '']" mode="simpledoc">
					<xsl:with-param name="type" select="'namespace'" />
				</xsl:apply-templates>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<!-- Capture left overs -->
	<xsl:template match="*" />

</xsl:stylesheet>