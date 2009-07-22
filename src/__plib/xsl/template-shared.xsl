<?xml version='1.0'?>
<xsl:stylesheet
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	version="1.0"
  xmlns:php="http://php.net/xsl"
  xsl:extension-element-prefixes="php"
	exclude-result-prefixes="php xsl"
>

	<!-- {{{ Docblock related templates 
	************************************************************************** -->

	<!-- Toplevel: Namespaces and modules -->
	<xsl:template match="docblock" mode="toplevel">
		<xsl:call-template name="docblock-description" />
		<ul>
			<xsl:apply-templates select="node()" mode="docblock" />
		</ul>
	</xsl:template>

	<!-- Method level: Functions, methods ... -->
	<xsl:template match="docblock" mode="methodlevel">
		<xsl:call-template name="docblock-description" />
		<ul>
			<xsl:apply-templates select="node()" mode="docblock" />
		</ul>
	</xsl:template>

	<!-- Property level -->
	<xsl:template match="docblock" mode="propertylevel">
		<xsl:if test="string-length(description)">
			<xsl:value-of select="description" disable-output-escaping="yes" />
		</xsl:if>
	</xsl:template>

	<!-- LICENSE -->
	<xsl:template match="license" mode="docblock">
		<xsl:if test="string-length(.)">
			<li>
				<span>License:</span>
				<xsl:text> </xsl:text>
				<a href="{$server.php_self}?__pliblicense=1"><xsl:value-of select="." /></a>
			</li>
		</xsl:if>
	</xsl:template>
	
	<!-- EXAMPLE -->
	<xsl:template match="example" mode="docblock">
		<xsl:if test="string-length(.)">
			<li>
				<span>Example:</span>
				<xsl:text> </xsl:text>
				<a href="{$server.php_self}?__plibexample={.}&amp;__plibmodule={ancestor::*[name() = 'module']/classpath}#example"><xsl:value-of select="." /></a>
			</li>
		</xsl:if>
	</xsl:template>

	<!-- VERSION -->
	<xsl:template match="version" mode="docblock">
		<li>Version: <xsl:value-of select="." /></li>
	</xsl:template>

	<!-- SINCE -->
	<xsl:template match="since" mode="docblock">
		<li>Since: <xsl:value-of select="." /></li>
	</xsl:template>

	<!-- AUTHOR -->
	<xsl:template match="author" mode="docblock">
		<li>Author: <xsl:value-of select="." disable-output-escaping="yes" /></li>
	</xsl:template>

	<!-- DEPENDS (special tag for PLib) -->
	<xsl:template match="depends" mode="docblock">
		<li>Depends on: 
			<ul>
				<xsl:apply-templates mode="docblock-param">
					<xsl:with-param name="prefix" select="''" />
				</xsl:apply-templates>
			</ul>
		</li>
	</xsl:template>
	
	<!-- USES -->
	<xsl:template match="uses" mode="docblock">
		<li>Uses: 
			<ul>
				<xsl:apply-templates mode="docblock-param">
					<xsl:with-param name="prefix" select="''" />
				</xsl:apply-templates>
			</ul>
		</li>
	</xsl:template>

	<!-- COPYRIGHT -->
	<xsl:template match="copyright" mode="docblock">
		<li>Copyright: <xsl:value-of select="." disable-output-escaping="yes"/></li>
	</xsl:template>

	<!-- LINK -->
	<xsl:template match="link" mode="docblock">
		<li>Link: <xsl:value-of disable-output-escaping="yes" select="." /></li>
	</xsl:template>
	
	<!--{{{ PARAMs -->
	<xsl:template match="params" mode="docblock">
		<xsl:apply-templates mode="docblock-param" />
	</xsl:template>
	
	<xsl:template match="param|depend|use|copyright" mode="docblock-param">
		<xsl:param name="prefix" select="'Param: '" />
		<li class='param'>
			<xsl:value-of select="$prefix"/>
			<xsl:for-each select="types/type">
				<xsl:value-of select="." disable-output-escaping="yes" />
				<xsl:if test="position() != last()">|</xsl:if>
			</xsl:for-each>
			<xsl:text> </xsl:text>
			<strong><xsl:value-of select="variable" disable-output-escaping="yes"/><xsl:text> </xsl:text></strong>
			<xsl:if test="string-length(description)">
				<div class="param-description">
					<xsl:value-of select="description" disable-output-escaping="yes" />
				</div>
			</xsl:if>
		</li>
	</xsl:template>
	<!-- }}} -->

	<!-- RETURN -->
	<xsl:template match="return" mode="docblock">
		<li>Returns:
			<xsl:call-template name="draw-datatypes">
				<xsl:with-param name="node" select="types" />
			</xsl:call-template>
			<xsl:if test="string-length(description)">
				<div class="param-description">
					<xsl:value-of select="description" disable-output-escaping="yes" />
				</div>
			</xsl:if>
		</li>
	</xsl:template>
	
	<!-- SEE -->
	<xsl:template match="see" mode="docblock">
		<li>See:
			<xsl:call-template name="draw-datatypes">
				<xsl:with-param name="node" select="types" />
			</xsl:call-template>
			<xsl:if test="string-length(description)">
				<div class="param-description">
					<xsl:value-of select="description" disable-output-escaping="yes" />
				</div>
			</xsl:if>
		</li>
	</xsl:template>
	
	<!-- Throws -->
	<xsl:template match="throws" mode="docblock">
		<li>Throws:
			<xsl:call-template name="draw-datatypes">
				<xsl:with-param name="node" select="types" />
			</xsl:call-template>
		</li>
	</xsl:template>	
	
	<!-- Deprecated -->
	<xsl:template match="deprecated" mode="docblock">
		<li><span>Deprecated: </span> <xsl:value-of select="." disable-output-escaping="yes"/></li>
	</xsl:template>	
	
	<!-- TODO -->
	<xsl:template match="todo" mode="docblock">
		<li><span>Todo: </span> <xsl:value-of select="." disable-output-escaping="yes"/></li>
	</xsl:template>	
	
	<!-- DESCRIPTION -->
	<xsl:template name="docblock-description">
		<xsl:if test="string-length(description)">
			<xsl:value-of select="description" disable-output-escaping="yes" />
			<hr/>
		</xsl:if>
	</xsl:template>
	
	<!-- Fallback -->
	<xsl:template match="*" mode="docblock" />

	<!-- }}} -->

	<!-- {{{ Draw the namespace -->
	<xsl:template name="draw-namespace">
		<xsl:variable name="namespace-name">
			<xsl:call-template name="find-namespace" />
		</xsl:variable>
		<xsl:variable name="namespace" select="//namespace[@name = $namespace-name]" />
		<div class="namespace">
			<h2>
				<span>Namespace:</span>
				<xsl:text> </xsl:text>
				<xsl:choose>
					<xsl:when test="string-length($namespace/@name)">
						<a href="{$server.php_self}?__plibnamespace={$namespace/@name}">
							<xsl:value-of select="$namespace/@name" />
						</a>
					</xsl:when>
					<xsl:otherwise>
						<a href="{$server.php_self}">Root</a>
					</xsl:otherwise>
				</xsl:choose>
			</h2>
		</div>
	</xsl:template>
	<!-- }}} -->

	<!-- {{{ Find out what namespace to highlight in the menu
	************************************************************************** -->
	<xsl:template name="find-namespace">
		<xsl:choose>
			<xsl:when test="$form.__plibnamespace">
				<xsl:value-of select="$form.__plibnamespace" />
			</xsl:when>
			<xsl:when test="$form.__plibmodule">
				<xsl:value-of select="//namespace[module/classpath = $form.__plibmodule]/@name" />
			</xsl:when>
			<xsl:when test="$form.__plibclass">
				<xsl:value-of select="//namespace[module/class/name = $form.__plibclass]/@name" />
			</xsl:when>
			<xsl:when test="$form.__plibfunction">
				<xsl:value-of select="//namespace[module/function/name = $form.__plibfunction]/@name" />
			</xsl:when>
			<xsl:when test="$plib.class">
				<xsl:value-of select="//namespace[module/class/name = $class]/@name" />
			</xsl:when>
		</xsl:choose>
	</xsl:template>
	<!-- }}} -->

	<xsl:template name="draw-method">
		<xsl:param name="draw-definition" select="true()" />
		<xsl:param name="linkify" select="false()" />

		<xsl:variable name="return-type">
			<xsl:call-template name="draw-datatypes">
				<xsl:with-param name="node" select="docblock/return/types" />
			</xsl:call-template>
		</xsl:variable>
		<xsl:choose>
			<xsl:when test="string-length($return-type)">
				<span class="return-type">
					<xsl:value-of select="$return-type" disable-output-escaping="yes"/>
				</span>
			</xsl:when><xsl:otherwise>
				<span class="return-type">void</span>
			</xsl:otherwise>
		</xsl:choose>
		<xsl:text> </xsl:text>
		<xsl:if test="abstract = 1">
			<span class="modifier">abstract</span><xsl:text> </xsl:text>
		</xsl:if>
		<xsl:if test="string-length(visibility)">
			<span class="modifier"><xsl:value-of select="visibility" /></span><xsl:text> </xsl:text>
		</xsl:if>
		<xsl:if test="static = 1">
			<span class="modifier">static</span><xsl:text> </xsl:text>
		</xsl:if>
		<span class="instance-name">
			<xsl:choose>
				<xsl:when test="$linkify">
					<a href="#method-{name}"><xsl:value-of select="name"/></a>(<xsl:call-template name="draw-params"/>)
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="name" />
				</xsl:otherwise>
			</xsl:choose>
		</span>
		<xsl:if test="inherited != 1 and $draw-definition">
			<br/>
			<small class="dim">
				<xsl:value-of select="ancestor::*[name() = 'class']/name" 
				/>::<xsl:value-of select="name"/>(<xsl:call-template name="draw-params"/>)
			</small>
		</xsl:if>
	</xsl:template>

	<xsl:template name="draw-datatypes">
		<xsl:param name="node" />
		<xsl:for-each select="$node/type">
			<xsl:value-of select="." disable-output-escaping="yes" />
			<xsl:if test="position() != last()">|</xsl:if>
		</xsl:for-each>
	</xsl:template>

	<xsl:template name="draw-params">
		<xsl:for-each select="params/param">
			<xsl:variable name="pos" select="position()" />
			<xsl:variable name="datatypes">
				<xsl:call-template name="draw-datatypes">
					<xsl:with-param name="node" select="parent::node()/preceding-sibling::*[name() = 'docblock']/params/param[position() = $pos]/node()"/>
				</xsl:call-template>
			</xsl:variable>
			<span class="param">
				<xsl:if test="optional = 1"> [</xsl:if>
				<xsl:if test="position() &gt; 1">,<xsl:text> </xsl:text></xsl:if>
				<xsl:value-of select="$datatypes" disable-output-escaping="yes"/>
				<xsl:text> </xsl:text>
				<span><xsl:if test="string-length(reference)">&amp;</xsl:if>$<xsl:value-of select="name" /></span>
				<xsl:if test="string-length(value)">
					<span>=</span>
					<xsl:choose>
						<xsl:when test="string-length(value)"><xsl:value-of select="value"/></xsl:when>
					</xsl:choose>
				</xsl:if>
				<xsl:if test="optional = 1">]</xsl:if>
			</span>
		</xsl:for-each>
	</xsl:template>
	
	<xsl:template match="method" mode="summary">
		<xsl:if test="visibility != 'private' or $plib.debug = true()">
			<li>
				<xsl:call-template name="draw-method">
					<xsl:with-param name="draw-definition" select="false()" />
					<xsl:with-param name="linkify" select="true()"/>
				</xsl:call-template>
			</li>
		</xsl:if>
	</xsl:template>
	
	<xsl:template name="draw-property">
		<xsl:param name="extended" select="true()"/>
		<xsl:param name="linkify" select="false()"/>

		<xsl:if test="string-length(visibility)">
			<span class="modifier"><xsl:value-of select="visibility"/></span>
			<xsl:text> </xsl:text>
		</xsl:if>
		<xsl:if test="static = 1">
			<span class="modifier">static</span><xsl:text> </xsl:text>
			<xsl:text> </xsl:text>
		</xsl:if>
		<xsl:if test="string-length(type)">
			<span class="modifier">
				<xsl:choose>
					<xsl:when test="string-length(docblock/var)">
						<xsl:value-of select="docblock/var"/>
					</xsl:when>
					<xsl:when test="string-length(docblock/staticvar)">
						<xsl:value-of select="docblock/staticvar"/>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="type"/>
					</xsl:otherwise>
				</xsl:choose>
			</span>
			<xsl:text> </xsl:text>
		</xsl:if>
		<span class="instance-name">
			<xsl:choose>
				<xsl:when test="$linkify">
					<a href="#property-{name}">$<xsl:value-of select="name"/></a>
				</xsl:when>
				<xsl:otherwise>
					$<xsl:value-of select="name"/>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:if test="$extended = true()">
				<xsl:choose>
					<xsl:when test="string-length(value) &gt; 0">=
						<xsl:choose>
							<xsl:when test="type = 'string'">"<xsl:value-of select="value"/>";</xsl:when>
							<xsl:when test="type = 'int' or type = 'float'"><xsl:value-of select="value"/>;</xsl:when>
							<xsl:otherwise><xsl:value-of select="value" />;</xsl:otherwise>
						</xsl:choose>
					</xsl:when>
				</xsl:choose>
			</xsl:if>
		</span>
	</xsl:template>

	<xsl:template match="property" mode="summary">
		<xsl:if test="visibility != 'private' or $plib.debug = true()">
			<li>
				<xsl:call-template name="draw-property">
					<xsl:with-param name="extended" select="false()" />
					<xsl:with-param name="linkify" select="true()" />
				</xsl:call-template>
			</li>
		</xsl:if>
	</xsl:template>
	
	<xsl:template name="todolist">
		<hr/>
		<h2 class="todo">TODO <small><a href="{$server.php_self}" id="toggle-todo">Show</a></small></h2>
		<div id="todo">
			<dl class="todo">
				<xsl:apply-templates select="//docblock/todo" mode="todolist" />
			</dl>
		</div>
		<hr/>
	</xsl:template>
	
	<xsl:template match="todo" mode="todolist">
		<dt><xsl:call-template name="draw-todo-struct">
			<xsl:with-param name="pnode" select="parent::node()/parent::node()" />
		</xsl:call-template></dt>
		<dd><xsl:value-of select="." disable-output-escaping="yes"/></dd>
	</xsl:template>
	
	<xsl:template name="draw-todo-struct">
		<xsl:param name="pnode" />
		<xsl:choose>
			<xsl:when test="name($pnode) != 'module'">
				<xsl:choose>
					<xsl:when test="name($pnode) = 'property'">
						in <em>property</em><xsl:text> </xsl:text>
						<a href="?__plibclass={$pnode/parent::node()/name}#property-{$pnode/name}"><xsl:value-of select="$pnode/name"/></a>
					</xsl:when>
					<xsl:when test="name($pnode) = 'method'">
						in <em>method</em><xsl:text> </xsl:text>
						<a href="?__plibclass={$pnode/parent::node()/parent::node()/name}#method-{$pnode/name}"><xsl:value-of select="$pnode/name"/></a>
					</xsl:when>
					<xsl:when test="name($pnode) = 'function'">
						in <em>function</em>
						<xsl:text> </xsl:text><a href="?__plibfunction={$pnode/name}#function-{$pnode/name}"><xsl:value-of select="$pnode/name"/></a>
					</xsl:when>
					<xsl:when test="name($pnode) = 'class'">
						in <em>class</em><xsl:text> </xsl:text>
						<a href="?__plibclass={$pnode/name}#class-{$pnode/name}"><xsl:value-of select="$pnode/name"/></a>
					</xsl:when>
				</xsl:choose>
				
				<xsl:call-template name="draw-todo-struct">
					<xsl:with-param name="pnode" select="$pnode/parent::node()"/>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				in <em>module</em><xsl:text> </xsl:text>
				<a href="?__plibmodule={$pnode/classpath}"><xsl:value-of select="$pnode/classpath"/></a>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
</xsl:stylesheet>