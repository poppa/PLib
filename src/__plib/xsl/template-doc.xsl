<?xml version='1.0'?>
<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  version="1.0"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
  exclude-result-prefixes="php xsl"
>

	<!-- {{{ Match namepace module -->
	<xsl:template match="namespace[module]" mode="simpledoc">
		<xsl:param name="type" />
		<xsl:apply-templates select="module" mode="simpledoc">
			<xsl:with-param name="type" select="$type" />
			<xsl:sort select="name"/>
		</xsl:apply-templates>
		
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match module -->
	<xsl:template match="module" mode="simpledoc">
		<xsl:param name="type" />
		
		<div class="docblock">
			<h3>
				<span>Module:</span>
				<xsl:text> </xsl:text>
				<a href="{$server.php_self}?__plibmodule={classpath}"><xsl:value-of select="classpath" /></a>
				<xsl:if test="$plib.source-view = true()">
					<xsl:text> </xsl:text>
					<small>
						<xsl:choose>
							<xsl:when test="$form.__plibsource">
								(<a href="{$server.php_self}?__plibmodule={classpath}">
									<span>Close source</span>
								</a>)
							</xsl:when>
							<xsl:otherwise>
								(<a href="{$server.php_self}?__plibmodule={classpath}&amp;__plibsource=1#source-view">
									<span>View source</span>
								</a>)
							</xsl:otherwise>
						</xsl:choose>
					</small>
				</xsl:if>
			</h3>
			<xsl:apply-templates select="docblock" mode="toplevel" />
			<xsl:if test="$type = 'module'">
				<hr/>
				<h4>Classes</h4>
				<ul class="linklist">
					<xsl:apply-templates mode="linklist" select="class">
						<xsl:sort select="name"/>
					</xsl:apply-templates>
				</ul>
				<xsl:if test="count(function) &gt; 0">
					<h4>Functions</h4>
					<ul class="linklist">
						<xsl:apply-templates mode="linklist" select="function">
							<xsl:sort select="name"/>
						</xsl:apply-templates>
					</ul>
				</xsl:if>
			</xsl:if>
		</div>
		<xsl:if test="$form.__plibsource and $plib.source-view">
			<div class="code" id="source-view">
				<xsl:copy-of select="php:function('PLib::XSL_HighlightSourceFile', string(classpath))"/>
			</div>
			<p>
				<a href="#top">To the top</a> | <a href="{$server.php_self}?__plibmodule={classpath}">Close source</a>
			</p>
		</xsl:if>
		<xsl:if test="$form.__plibexample and $form.__plibmodule = classpath">
			<div class="code" id="example">
				<xsl:copy-of select="php:function('PLib::XSL_HighlightExampleFile', string($form.__plibexample))"/>
			</div>
			<p><a href="#top">To the top</a> | <a href="{$server.php_self}?__plibmodule={classpath}">Close example</a></p>
		</xsl:if>
		<xsl:if test="$type = 'module'">
			<xsl:if test="count(class) &gt; 0">
				<div class="instance-header">
					<p>Classes of module <xsl:value-of select="classpath" /></p>
				</div>
				<xsl:apply-templates select="class" mode="simpledoc">
					<xsl:sort select="name"/>
				</xsl:apply-templates>
			</xsl:if>
			<xsl:if test="count(function) &gt; 0">
				<div class="instance-header">
					<p>Functions of module <xsl:value-of select="classpath" /></p>
				</div>
			</xsl:if>
			<xsl:apply-templates select="function" mode="simpledoc">
				<xsl:sort select="name"/>
			</xsl:apply-templates>
		</xsl:if>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match class -->
	<xsl:template match="class" mode="simpledoc">
		<xsl:param name="type" />
		<div class="class-instance" id="class-{name}">
			<h4>
				<xsl:if test="instantiable != 1">
					<xsl:attribute name="class">hidden</xsl:attribute>
					<small>! Not instantiable</small><br/>
				</xsl:if>
				<span><xsl:value-of select="name" /></span>
				<xsl:text> </xsl:text>
				<small>(<xsl:value-of select="string" />)</small>
			</h4>
			<div class="docblock2">
				<xsl:apply-templates select="docblock" mode="toplevel" />
			</div>
			<hr/>
			<div class="summary">
				<h5>Member summary</h5>
				<ul>
					<xsl:apply-templates select="properties/property" mode="summary">
						<xsl:sort select="name" />
					</xsl:apply-templates>
				</ul>
			</div>
			<div class="summary">
				<h5>Method summary</h5>
				<ul>
					<xsl:apply-templates select="methods/method" mode="summary">
						<xsl:sort select="name" />
					</xsl:apply-templates>
				</ul>
			</div>
		</div>
		<div class="instance-header">
			<p><span>Members of <code><xsl:value-of select="name"/></code></span></p>
		</div>
		<xsl:apply-templates select="properties" mode="simpledoc">
			<xsl:sort select="property/name"/>
		</xsl:apply-templates>
		<div class="instance-header">
			<p><span>Methods of <code><xsl:value-of select="name"/></code></span></p>
		</div>
		<xsl:apply-templates select="methods" mode="simpledoc"/>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match methods -->
	<xsl:template match="methods" mode="simpledoc">
		<xsl:apply-templates select="method[inherited != 1]" mode="simpledoc"/>
		<xsl:if test="method[inherited = 1]">
			<div class="inherited-methods">
				<p class="header">Inherited methods</p>
				<xsl:apply-templates select="method[inherited = 1]" mode="simpledoc" />
			</div>
		</xsl:if>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match method -->
	<xsl:template match="method" mode="simpledoc">
		<xsl:param name="type" />
		<xsl:if test="visibility != 'private' or $plib.debug = true()">
			<div class="method" id="method-{name}">
				<div class="docblock">
					<p><xsl:call-template name="draw-method" /></p>
					<xsl:apply-templates select="docblock" mode="methodlevel" />
				</div>
			</div>
		</xsl:if>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match inherited method -->
	<xsl:template match="method[inherited = 1]" mode="simpledoc">
		<p><small><xsl:call-template name="draw-method" /><xsl:text> </xsl:text>
		<em>defined in <xsl:value-of select="declaring-class"/></em></small></p>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match functions -->
	<xsl:template match="function" mode="simpledoc">
		<xsl:param name="type" />
		<div class="method" id="function-{name}">
			<div class="docblock">
				<p>
					<xsl:call-template name="draw-method"/>(<xsl:call-template name="draw-params">
						<xsl:with-param name="node" select="docblock/params" />	
					</xsl:call-template>)
				</p>
				<div class="docblock2">
					<xsl:apply-templates select="docblock" mode="methodlevel" />
				</div>
			</div>
		</div>
	</xsl:template>
	<!-- }}} -->
	
	<!-- {{{ Match properties -->
	<xsl:template match="properties" mode="simpledoc">
		<xsl:apply-templates select="property" mode="simpledoc" />
		<xsl:if test="method[inherited != 1]">
			<div class="inherited-properties">
				<p class="header">Inherited properties</p>
				<xsl:apply-templates select="property[inherited = 1]" mode="simpledoc" />
			</div>
		</xsl:if>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Match property -->
	<xsl:template match="property" mode="simpledoc">
		<xsl:param name="type" />
		<xsl:if test="visibility != 'private' or $plib.debug = true()">
			<div class="property" id="property-{name}">
				<div class="docblock">
					<xsl:call-template name="draw-property" />
					<div class="docblock2">
						<xsl:apply-templates select="docblock" mode="methodlevel" />
					</div>
				</div>
			</div>
		</xsl:if>
	</xsl:template>
	<!-- }}} -->
	
	<!-- 
	==============================================================================
	
		Special
	
	========================================================================== -->

	<!-- {{{ Match classes in linklist mode -->
	<xsl:template match="class" mode="linklist">
		<li>
			<a href="#class-{name}"><xsl:value-of select="name"/></a>
		</li>
	</xsl:template>
	<!-- }}} -->
	
	<!-- {{{ Match functions in linklist mode -->
	<xsl:template match="function" mode="linklist">
		<li>
			<a href="#function-{name}"><xsl:value-of select="name"/></a>
		</li>
	</xsl:template>
	<!-- }}} -->
	
	<!-- {{{ Match classes in infolist mode -->
	<xsl:template match="class" mode="infolist">
		<li>
			<a href="{$server.php_self}?__plibclass={name}#class-{name}"><xsl:value-of select="name"/></a>
		</li>
	</xsl:template>
	<!-- }}} -->
	
	<!-- {{{ Match functions in infolist mode -->
	<xsl:template match="function" mode="infolist">
		<li>
			<a href="{$server.php_self}?__plibfunction={name}#function-{name}"><xsl:value-of select="name"/></a>
		</li>
	</xsl:template>
	<!-- }}} -->
	
</xsl:stylesheet>