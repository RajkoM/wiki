<?php
/**
 * @defgroup Skins Skins
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 1 );
}

/**
 * The main skin class that provide methods and properties for all other skins.
 * This base class is also the "Standard" skin.
 *
 * See docs/skin.txt for more information.
 *
 * @ingroup Skins
 */
class Skin extends Linker {
	/**#@+
	 * @private
	 */
	var $mWatchLinkNum = 0; // Appended to end of watch link id's
	// How many search boxes have we made?  Avoid duplicate id's.
	protected $searchboxes = '';
	/**#@-*/
	protected $mRevisionId; // The revision ID we're looking at, null if not applicable.
	protected $skinname = 'standard';
	// @todo Fixme: should be protected :-\
	var $mTitle = null;

	/** Constructor, call parent constructor */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Fetch the set of available skins.
	 * @return array of strings
	 */
	static function getSkinNames() {
		global $wgValidSkinNames;
		static $skinsInitialised = false;

		if ( !$skinsInitialised ) {
			# Get a list of available skins
			# Build using the regular expression '^(.*).php$'
			# Array keys are all lower case, array value keep the case used by filename
			#
			wfProfileIn( __METHOD__ . '-init' );

			global $wgStyleDirectory;

			$skinDir = dir( $wgStyleDirectory );

			# while code from www.php.net
			while ( false !== ( $file = $skinDir->read() ) ) {
				// Skip non-PHP files, hidden files, and '.dep' includes
				$matches = array();

				if ( preg_match( '/^([^.]*)\.php$/', $file, $matches ) ) {
					$aSkin = $matches[1];
					$wgValidSkinNames[strtolower( $aSkin )] = $aSkin;
				}
			}
			$skinDir->close();
			$skinsInitialised = true;
			wfProfileOut( __METHOD__ . '-init' );
		}
		return $wgValidSkinNames;
	}

	/**
	 * Fetch the list of usable skins in regards to $wgSkipSkins.
	 * Useful for Special:Preferences and other places where you
	 * only want to show skins users _can_ use.
	 * @return array of strings
	 */
	public static function getUsableSkins() {
		global $wgSkipSkins;

		$usableSkins = self::getSkinNames();

		foreach ( $wgSkipSkins as $skip ) {
			unset( $usableSkins[$skip] );
		}

		return $usableSkins;
	}

	/**
	 * Normalize a skin preference value to a form that can be loaded.
	 * If a skin can't be found, it will fall back to the configured
	 * default (or the old 'Classic' skin if that's broken).
	 * @param $key String: 'monobook', 'standard', etc.
	 * @return string
	 */
	static function normalizeKey( $key ) {
		global $wgDefaultSkin;

		$skinNames = Skin::getSkinNames();

		if ( $key == '' ) {
			// Don't return the default immediately;
			// in a misconfiguration we need to fall back.
			$key = $wgDefaultSkin;
		}

		if ( isset( $skinNames[$key] ) ) {
			return $key;
		}

		// Older versions of the software used a numeric setting
		// in the user preferences.
		$fallback = array(
			0 => $wgDefaultSkin,
			1 => 'nostalgia',
			2 => 'cologneblue'
		);

		if ( isset( $fallback[$key] ) ) {
			$key = $fallback[$key];
		}

		if ( isset( $skinNames[$key] ) ) {
			return $key;
		} else if ( isset( $skinNames[$wgDefaultSkin] ) ) {
			return $wgDefaultSkin;
		} else {
			return 'vector';
		}
	}

	/**
	 * Factory method for loading a skin of a given type
	 * @param $key String: 'monobook', 'standard', etc.
	 * @return Skin
	 */
	static function &newFromKey( $key ) {
		global $wgStyleDirectory;

		$key = Skin::normalizeKey( $key );

		$skinNames = Skin::getSkinNames();
		$skinName = $skinNames[$key];
		$className = 'Skin' . ucfirst( $key );

		# Grab the skin class and initialise it.
		if ( !class_exists( $className ) ) {
			// Preload base classes to work around APC/PHP5 bug
			$deps = "{$wgStyleDirectory}/{$skinName}.deps.php";

			if ( file_exists( $deps ) ) {
				include_once( $deps );
			}
			require_once( "{$wgStyleDirectory}/{$skinName}.php" );

			# Check if we got if not failback to default skin
			if ( !class_exists( $className ) ) {
				# DO NOT die if the class isn't found. This breaks maintenance
				# scripts and can cause a user account to be unrecoverable
				# except by SQL manipulation if a previously valid skin name
				# is no longer valid.
				wfDebug( "Skin class does not exist: $className\n" );
				$className = 'SkinVector';
				require_once( "{$wgStyleDirectory}/Vector.php" );
			}
		}
		$skin = new $className;
		return $skin;
	}

	/** @return string path to the skin stylesheet */
	function getStylesheet() {
		return 'common/wikistandard.css';
	}

	/** @return string skin name */
	public function getSkinName() {
		return $this->skinname;
	}

	function qbSetting() {
		global $wgOut, $wgUser;

		if ( $wgOut->isQuickbarSuppressed() ) {
			return 0;
		}

		$q = $wgUser->getOption( 'quickbar', 0 );

		return $q;
	}

	function initPage( OutputPage $out ) {
		global $wgFavicon, $wgAppleTouchIcon, $wgEnableAPI;

		wfProfileIn( __METHOD__ );

		# Generally the order of the favicon and apple-touch-icon links
		# should not matter, but Konqueror (3.5.9 at least) incorrectly
		# uses whichever one appears later in the HTML source. Make sure
		# apple-touch-icon is specified first to avoid this.
		if ( false !== $wgAppleTouchIcon ) {
			$out->addLink( array( 'rel' => 'apple-touch-icon', 'href' => $wgAppleTouchIcon ) );
		}

		if ( false !== $wgFavicon ) {
			$out->addLink( array( 'rel' => 'shortcut icon', 'href' => $wgFavicon ) );
		}

		# OpenSearch description link
		$out->addLink( array(
			'rel' => 'search',
			'type' => 'application/opensearchdescription+xml',
			'href' => wfScript( 'opensearch_desc' ),
			'title' => wfMsgForContent( 'opensearch-desc' ),
		) );

		if ( $wgEnableAPI ) {
			# Real Simple Discovery link, provides auto-discovery information
			# for the MediaWiki API (and potentially additional custom API
			# support such as WordPress or Twitter-compatible APIs for a
			# blogging extension, etc)
			$out->addLink( array(
				'rel' => 'EditURI',
				'type' => 'application/rsd+xml',
				'href' => wfExpandUrl( wfAppendQuery( wfScript( 'api' ), array( 'action' => 'rsd' ) ) ),
			) );
		}

		$this->addMetadataLinks( $out );

		$this->mRevisionId = $out->mRevisionId;

		$this->preloadExistence();

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Preload the existence of three commonly-requested pages in a single query
	 */
	function preloadExistence() {
		global $wgUser;

		// User/talk link
		$titles = array( $wgUser->getUserPage(), $wgUser->getTalkPage() );

		// Other tab link
		if ( $this->mTitle->getNamespace() == NS_SPECIAL ) {
			// nothing
		} elseif ( $this->mTitle->isTalkPage() ) {
			$titles[] = $this->mTitle->getSubjectPage();
		} else {
			$titles[] = $this->mTitle->getTalkPage();
		}

		$lb = new LinkBatch( $titles );
		$lb->setCaller( __METHOD__ );
		$lb->execute();
	}

	/**
	 * Adds metadata links below to the HTML output.
	 * <ol>
	 *  <li>Creative Commons
	 *   <br />See http://wiki.creativecommons.org/Extend_Metadata.
	 *  </li>
	 *  <li>Dublin Core</li>
	 *  <li>Use hreflang to specify canonical and alternate links
	 *   <br />See http://www.google.com/support/webmasters/bin/answer.py?answer=189077
	 *  </li>
	 *  <li>Copyright</li>
	 * <ol>
	 * 
	 * @param $out Object: instance of OutputPage
	 */
	function addMetadataLinks( OutputPage $out ) {
		global $wgEnableDublinCoreRdf, $wgEnableCreativeCommonsRdf;
		global $wgDisableLangConversion, $wgCanonicalLanguageLinks, $wgContLang;
		global $wgRightsPage, $wgRightsUrl;

		if ( $out->isArticleRelated() ) {
			# note: buggy CC software only reads first "meta" link
			if ( $wgEnableCreativeCommonsRdf ) {
				$out->addMetadataLink( array(
					'title' => 'Creative Commons',
					'type' => 'application/rdf+xml',
					'href' => $this->mTitle->getLocalURL( 'action=creativecommons' ) )
				);
			}

			if ( $wgEnableDublinCoreRdf ) {
				$out->addMetadataLink( array(
					'title' => 'Dublin Core',
					'type' => 'application/rdf+xml',
					'href' => $this->mTitle->getLocalURL( 'action=dublincore' ) )
				);
			}
		}

		if ( !$wgDisableLangConversion && $wgCanonicalLanguageLinks
			&& $wgContLang->hasVariants() ) {

			$urlvar = $wgContLang->getURLVariant();

			if ( !$urlvar ) {
				$variants = $wgContLang->getVariants();
				foreach ( $variants as $_v ) {
					$out->addLink( array(
						'rel' => 'alternate',
						'hreflang' => $_v,
						'href' => $this->mTitle->getLocalURL( '', $_v ) )
					);
				}
			} else {
				$out->addLink( array(
					'rel' => 'canonical',
					'href' => $this->mTitle->getFullURL() )
				);
			}
		}
		
		$copyright = '';
		if ( $wgRightsPage ) {
			$copy = Title::newFromText( $wgRightsPage );

			if ( $copy ) {
				$copyright = $copy->getLocalURL();
			}
		}

		if ( !$copyright && $wgRightsUrl ) {
			$copyright = $wgRightsUrl;
		}

		if ( $copyright ) {
			$out->addLink( array(
				'rel' => 'copyright',
				'href' => $copyright )
			);
		}
	}

	/**
	 * Set some local variables
	 */
	protected function setMembers() {
		global $wgUser;
		$this->mUser = $wgUser;
		$this->userpage = $wgUser->getUserPage()->getPrefixedText();
		$this->usercss = false;
	}

	/**
	 * Set the title
	 * @param $t Title object to use
	 */
	public function setTitle( $t ) {
		$this->mTitle = $t;
	}

	/** Get the title */
	public function getTitle() {
		return $this->mTitle;
	}

	/**
	 * Outputs the HTML generated by other functions.
	 * @param $out Object: instance of OutputPage
	 */
	function outputPage( OutputPage $out ) {
		global $wgDebugComments;
		wfProfileIn( __METHOD__ );

		$this->setMembers();
		$this->initPage( $out );

		// See self::afterContentHook() for documentation
		$afterContent = $this->afterContentHook();

		$out->out( $out->headElement( $this ) );

		if ( $wgDebugComments ) {
			$out->out( "<!-- Wiki debugging output:\n" .
			  $out->mDebugtext . "-->\n" );
		}

		$out->out( $this->beforeContent() );

		$out->out( $out->mBodytext . "\n" );

		$out->out( $this->afterContent() );

		$out->out( $afterContent );

		$out->out( $this->bottomScripts( $out ) );

		$out->out( wfReportTime() );

		$out->out( "\n</body></html>" );
		wfProfileOut( __METHOD__ );
	}

	static function makeVariablesScript( $data ) {
		if ( $data ) {
			return Html::inlineScript(
				ResourceLoader::makeLoaderConditionalScript( ResourceLoader::makeConfigSetScript( $data ) )
			);
		} else {
			return '';
		} 
	}

	/**
	 * Make a <script> tag containing global variables
	 * @param $skinName string Name of the skin
	 * The odd calling convention is for backwards compatibility
	 * @todo FIXME: Make this not depend on $wgTitle!
	 * 
	 * Do not add things here which can be evaluated in ResourceLoaderStartupScript - in other words, without state.
	 * You will only be adding bloat to the page and causing page caches to have to be purged on configuration changes.
	 */
	static function makeGlobalVariablesScript( $skinName ) {
		global $wgTitle, $wgUser, $wgRequest, $wgArticle, $wgOut, $wgUseAjax, $wgEnableMWSuggest;
		
		$ns = $wgTitle->getNamespace();
		$nsname = MWNamespace::exists( $ns ) ? MWNamespace::getCanonicalName( $ns ) : $wgTitle->getNsText();
		$vars = array(
			'wgCanonicalNamespace' => $nsname,
			'wgCanonicalSpecialPageName' => $ns == NS_SPECIAL ?
				SpecialPage::resolveAlias( $wgTitle->getDBkey() ) : false, # bug 21115
			'wgNamespaceNumber' => $wgTitle->getNamespace(),
			'wgPageName' => $wgTitle->getPrefixedDBKey(),
			'wgTitle' => $wgTitle->getText(),
			'wgAction' => $wgRequest->getText( 'action', 'view' ),
			'wgArticleId' => $wgTitle->getArticleId(),
			'wgIsArticle' => $wgOut->isArticle(),
			'wgUserName' => $wgUser->isAnon() ? null : $wgUser->getName(),
			'wgUserGroups' => $wgUser->getEffectiveGroups(),
			'wgCurRevisionId' => isset( $wgArticle ) ? $wgArticle->getLatest() : 0,
			'wgCategories' => $wgOut->getCategories(),
			'wgBreakFrames' => $wgOut->getFrameOptions() == 'DENY',
		);
		foreach ( $wgTitle->getRestrictionTypes() as $type ) {
			$vars['wgRestriction' . ucfirst( $type )] = $wgTitle->getRestrictions( $type );
		}
		if ( $wgUseAjax && $wgEnableMWSuggest && !$wgUser->getOption( 'disablesuggest', false ) ) {
			$vars['wgSearchNamespaces'] = SearchEngine::userNamespaces( $wgUser );
		}
		
		// Allow extensions to add their custom variables to the global JS variables
		wfRunHooks( 'MakeGlobalVariablesScript', array( &$vars ) );
		
		return self::makeVariablesScript( $vars );
	}

	/**
	 * To make it harder for someone to slip a user a fake
	 * user-JavaScript or user-CSS preview, a random token
	 * is associated with the login session. If it's not
	 * passed back with the preview request, we won't render
	 * the code.
	 *
	 * @param $action String: 'edit', 'submit' etc.
	 * @return bool
	 */
	public function userCanPreview( $action ) {
		global $wgRequest, $wgUser;

		if ( $action != 'submit' ) {
			return false;
		}
		if ( !$wgRequest->wasPosted() ) {
			return false;
		}
		if ( !$this->mTitle->userCanEditCssSubpage() ) {
			return false;
		}
		if ( !$this->mTitle->userCanEditJsSubpage() ) {
			return false;
		}

		return $wgUser->matchEditToken(
			$wgRequest->getVal( 'wpEditToken' ) );
	}

	/**
	 * Generated JavaScript action=raw&gen=js
	 * This returns MediaWiki:Common.js and MediaWiki:[Skinname].js concate-
	 * nated together.  For some bizarre reason, it does *not* return any
	 * custom user JS from subpages.  Huh?
	 *
	 * There's absolutely no reason to have separate Monobook/Common JSes.
	 * Any JS that cares can just check the skin variable generated at the
	 * top.  For now Monobook.js will be maintained, but it should be consi-
	 * dered deprecated.
	 *
	 * @param $skinName String: If set, overrides the skin name
	 * @return string
	 */
	public function generateUserJs( $skinName = null ) {
		
		// Stub - see ResourceLoaderSiteModule, CologneBlue, Simple and Standard skins override this
		
		return '';
	}

	/**
	 * Generate user stylesheet for action=raw&gen=css
	 */
	public function generateUserStylesheet() {
		
		// Stub - see ResourceLoaderUserModule, CologneBlue, Simple and Standard skins override this
		
		return '';
	}

	/**
	 * Split for easier subclassing in SkinSimple, SkinStandard and SkinCologneBlue
	 * Anything in here won't be generated if $wgAllowUserCssPrefs is false.
	 */
	protected function reallyGenerateUserStylesheet() {
		
		// Stub - see  ResourceLoaderUserModule, CologneBlue, Simple and Standard skins override this
		
		return '';
	}

	/**
	 * @private
	 */
	function setupUserCss( OutputPage $out ) {
		global $wgRequest;
		global $wgUseSiteCss, $wgAllowUserCss, $wgAllowUserCssPrefs;

		wfProfileIn( __METHOD__ );

		$this->setupSkinUserCss( $out );
		// Add any extension CSS
		foreach ( $out->getExtStyle() as $url ) {
			$out->addStyle( $url );
		}

		// Per-site custom styles
		if ( $wgUseSiteCss ) {
			$out->addModuleStyles( 'site' );
		}

		// Per-user custom styles
		if ( $wgAllowUserCss ) {
			if ( $this->mTitle->isCssSubpage() && $this->userCanPreview( $wgRequest->getVal( 'action' ) ) ) {
				// @FIXME: properly escape the cdata!
				$out->addInlineStyle( $wgRequest->getText( 'wpTextbox1' ) );
			} else {
				$out->addModuleStyles( 'user' );
			}
		}

		// Per-user preference styles
		if ( $wgAllowUserCssPrefs ) {
			$out->addModuleStyles( 'user.options' );
		}

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Get the query to generate a dynamic stylesheet
	 *
	 * @return array
	 */
	public static function getDynamicStylesheetQuery() {
		global $wgSquidMaxage;

		return array(
				'action' => 'raw',
				'maxage' => $wgSquidMaxage,
				'usemsgcache' => 'yes',
				'ctype' => 'text/css',
				'smaxage' => $wgSquidMaxage,
			);
	}

	/**
	 * Add skin specific stylesheets
	 * @param $out OutputPage
	 */
	function setupSkinUserCss( OutputPage $out ) {
		$out->addModuleStyles( 'mediawiki.legacy.shared' );
		$out->addModuleStyles( 'mediawiki.legacy.oldshared' );
		// TODO: When converting old skins to use ResourceLoader (or removing them) the following should be reconsidered
		$out->addStyle( $this->getStylesheet() );
		$out->addStyle( 'common/common_rtl.css', '', '', 'rtl' );
	}

	function getPageClasses( $title ) {
		$numeric = 'ns-' . $title->getNamespace();

		if ( $title->getNamespace() == NS_SPECIAL ) {
			$type = 'ns-special';
		} elseif ( $title->isTalkPage() ) {
			$type = 'ns-talk';
		} else {
			$type = 'ns-subject';
		}

		$name = Sanitizer::escapeClass( 'page-' . $title->getPrefixedText() );

		return "$numeric $type $name";
	}

	/**
	 * This will be called by OutputPage::headElement when it is creating the
	 * <body> tag, skins can override it if they have a need to add in any
	 * body attributes or classes of their own.
	 */
	function addToBodyAttributes( $out, &$bodyAttrs ) {
		// does nothing by default
	}

	/**
	 * URL to the logo
	 */
	function getLogo() {
		global $wgLogo;
		return $wgLogo;
	}

	/**
	 * This will be called immediately after the <body> tag.  Split into
	 * two functions to make it easier to subclass.
	 */
	function beforeContent() {
		return $this->doBeforeContent();
	}

	function doBeforeContent() {
		global $wgContLang;
		wfProfileIn( __METHOD__ );

		$s = '';
		$qb = $this->qbSetting();

		$langlinks = $this->otherLanguages();
		if ( $langlinks ) {
			$rows = 2;
			$borderhack = '';
		} else {
			$rows = 1;
			$langlinks = false;
			$borderhack = 'class="top"';
		}

		$s .= "\n<div id='content'>\n<div id='topbar'>\n" .
		  "<table border='0' cellspacing='0' width='98%'>\n<tr>\n";

		$shove = ( $qb != 0 );
		$left = ( $qb == 1 || $qb == 3 );

		if ( $wgContLang->isRTL() ) {
			$left = !$left;
		}

		if ( !$shove ) {
			$s .= "<td class='top' align='left' valign='top' rowspan='{$rows}'>\n" .
				$this->logoText() . '</td>';
		} elseif ( $left ) {
			$s .= $this->getQuickbarCompensator( $rows );
		}

		$l = $wgContLang->alignStart();
		$s .= "<td {$borderhack} align='$l' valign='top'>\n";

		$s .= $this->topLinks();
		$s .= '<p class="subtitle">' . $this->pageTitleLinks() . "</p>\n";

		$r = $wgContLang->alignEnd();
		$s .= "</td>\n<td {$borderhack} valign='top' align='$r' nowrap='nowrap'>";
		$s .= $this->nameAndLogin();
		$s .= "\n<br />" . $this->searchForm() . '</td>';

		if ( $langlinks ) {
			$s .= "</tr>\n<tr>\n<td class='top' colspan=\"2\">$langlinks</td>\n";
		}

		if ( $shove && !$left ) { # Right
			$s .= $this->getQuickbarCompensator( $rows );
		}

		$s .= "</tr>\n</table>\n</div>\n";
		$s .= "\n<div id='article'>\n";

		$notice = wfGetSiteNotice();

		if ( $notice ) {
			$s .= "\n<div id='siteNotice'>$notice</div>\n";
		}
		$s .= $this->pageTitle();
		$s .= $this->pageSubtitle();
		$s .= $this->getCategories();

		wfProfileOut( __METHOD__ );
		return $s;
	}

	function getCategoryLinks() {
		global $wgOut, $wgUseCategoryBrowser;
		global $wgContLang, $wgUser;

		if ( count( $wgOut->mCategoryLinks ) == 0 ) {
			return '';
		}

		# Separator
		$sep = wfMsgExt( 'catseparator', array( 'parsemag', 'escapenoentities' ) );

		// Use Unicode bidi embedding override characters,
		// to make sure links don't smash each other up in ugly ways.
		$dir = $wgContLang->getDir();
		$embed = "<span dir='$dir'>";
		$pop = '</span>';

		$allCats = $wgOut->getCategoryLinks();
		$s = '';
		$colon = wfMsgExt( 'colon-separator', 'escapenoentities' );

		if ( !empty( $allCats['normal'] ) ) {
			$t = $embed . implode( "{$pop} {$sep} {$embed}" , $allCats['normal'] ) . $pop;

			$msg = wfMsgExt( 'pagecategories', array( 'parsemag', 'escapenoentities' ), count( $allCats['normal'] ) );
			$s .= '<div id="mw-normal-catlinks">' .
				$this->link( Title::newFromText( wfMsgForContent( 'pagecategorieslink' ) ), $msg )
				. $colon . $t . '</div>';
		}

		# Hidden categories
		if ( isset( $allCats['hidden'] ) ) {
			if ( $wgUser->getBoolOption( 'showhiddencats' ) ) {
				$class = 'mw-hidden-cats-user-shown';
			} elseif ( $this->mTitle->getNamespace() == NS_CATEGORY ) {
				$class = 'mw-hidden-cats-ns-shown';
			} else {
				$class = 'mw-hidden-cats-hidden';
			}

			$s .= "<div id=\"mw-hidden-catlinks\" class=\"$class\">" .
				wfMsgExt( 'hidden-categories', array( 'parsemag', 'escapenoentities' ), count( $allCats['hidden'] ) ) .
				$colon . $embed . implode( "$pop $sep $embed", $allCats['hidden'] ) . $pop .
				'</div>';
		}

		# optional 'dmoz-like' category browser. Will be shown under the list
		# of categories an article belong to
		if ( $wgUseCategoryBrowser ) {
			$s .= '<br /><hr />';

			# get a big array of the parents tree
			$parenttree = $this->mTitle->getParentCategoryTree();
			# Skin object passed by reference cause it can not be
			# accessed under the method subfunction drawCategoryBrowser
			$tempout = explode( "\n", $this->drawCategoryBrowser( $parenttree, $this ) );
			# Clean out bogus first entry and sort them
			unset( $tempout[0] );
			asort( $tempout );
			# Output one per line
			$s .= implode( "<br />\n", $tempout );
		}

		return $s;
	}

	/**
	 * Render the array as a serie of links.
	 * @param $tree Array: categories tree returned by Title::getParentCategoryTree
	 * @param &skin Object: skin passed by reference
	 * @return String separated by &gt;, terminate with "\n"
	 */
	function drawCategoryBrowser( $tree, &$skin ) {
		$return = '';

		foreach ( $tree as $element => $parent ) {
			if ( empty( $parent ) ) {
				# element start a new list
				$return .= "\n";
			} else {
				# grab the others elements
				$return .= $this->drawCategoryBrowser( $parent, $skin ) . ' &gt; ';
			}

			# add our current element to the list
			$eltitle = Title::newFromText( $element );
			$return .=  $skin->link( $eltitle, $eltitle->getText() );
		}

		return $return;
	}

	function getCategories() {
		$catlinks = $this->getCategoryLinks();

		$classes = 'catlinks';

		global $wgOut, $wgUser;

		// Check what we're showing
		$allCats = $wgOut->getCategoryLinks();
		$showHidden = $wgUser->getBoolOption( 'showhiddencats' ) ||
						$this->mTitle->getNamespace() == NS_CATEGORY;

		if ( empty( $allCats['normal'] ) && !( !empty( $allCats['hidden'] ) && $showHidden ) ) {
			$classes .= ' catlinks-allhidden';
		}

		return "<div id='catlinks' class='$classes'>{$catlinks}</div>";
	}

	function getQuickbarCompensator( $rows = 1 ) {
		return "<td width='152' rowspan='{$rows}'>&#160;</td>";
	}

	/**
	 * This runs a hook to allow extensions placing their stuff after content
	 * and article metadata (e.g. categories).
	 * Note: This function has nothing to do with afterContent().
	 *
	 * This hook is placed here in order to allow using the same hook for all
	 * skins, both the SkinTemplate based ones and the older ones, which directly
	 * use this class to get their data.
	 *
	 * The output of this function gets processed in SkinTemplate::outputPage() for
	 * the SkinTemplate based skins, all other skins should directly echo it.
	 *
	 * Returns an empty string by default, if not changed by any hook function.
	 */
	protected function afterContentHook() {
		$data = '';

		if ( wfRunHooks( 'SkinAfterContent', array( &$data, $this ) ) ) {
			// adding just some spaces shouldn't toggle the output
			// of the whole <div/>, so we use trim() here
			if ( trim( $data ) != '' ) {
				// Doing this here instead of in the skins to
				// ensure that the div has the same ID in all
				// skins
				$data = "<div id='mw-data-after-content'>\n" .
					"\t$data\n" .
					"</div>\n";
			}
		} else {
			wfDebug( "Hook SkinAfterContent changed output processing.\n" );
		}

		return $data;
	}

	/**
	 * Generate debug data HTML for displaying at the bottom of the main content
	 * area.
	 * @return String HTML containing debug data, if enabled (otherwise empty).
	 */
	protected function generateDebugHTML() {
		global $wgShowDebug, $wgOut;

		if ( $wgShowDebug ) {
			$listInternals = $this->formatDebugHTML( $wgOut->mDebugtext );
			return "\n<hr />\n<strong>Debug data:</strong><ul style=\"font-family:monospace;\" id=\"mw-debug-html\">" .
				$listInternals . "</ul>\n";
		}

		return '';
	}

	private function formatDebugHTML( $debugText ) {
		$lines = explode( "\n", $debugText );
		$curIdent = 0;
		$ret = '<li>';

		foreach ( $lines as $line ) {
			$display = ltrim( $line );
			$ident = strlen( $line ) - strlen( $display );
			$diff = $ident - $curIdent;

			if ( $display == '' ) {
				$display = "\xc2\xa0";
			}

			if ( !$ident && $diff < 0 && substr( $display, 0, 9 ) != 'Entering ' && substr( $display, 0, 8 ) != 'Exiting ' ) {
				$ident = $curIdent;
				$diff = 0;
				$display = '<span style="background:yellow;">' . htmlspecialchars( $display ) . '</span>';
			} else {
				$display = htmlspecialchars( $display );
			}

			if ( $diff < 0 ) {
				$ret .= str_repeat( "</li></ul>\n", -$diff ) . "</li><li>\n";
			} elseif ( $diff == 0 ) {
				$ret .= "</li><li>\n";
			} else {
				$ret .= str_repeat( "<ul><li>\n", $diff );
			}
			$ret .= $display . "\n";

			$curIdent = $ident;
		}

		$ret .= str_repeat( '</li></ul>', $curIdent ) . '</li>';

		return $ret;
	}

	/**
	 * This gets called shortly before the </body> tag.
	 * @return String HTML to be put before </body>
	 */
	function afterContent() {
		$printfooter = "<div class=\"printfooter\">\n" . $this->printFooter() . "</div>\n";
		return $printfooter . $this->generateDebugHTML() . $this->doAfterContent();
	}

	/**
	 * This gets called shortly before the </body> tag.
	 * @param $out OutputPage object
	 * @return String HTML-wrapped JS code to be put before </body>
	 */
	function bottomScripts( $out ) {
		$bottomScriptText = "\n" . $out->getHeadScripts( $this );
		wfRunHooks( 'SkinAfterBottomScripts', array( $this, &$bottomScriptText ) );

		return $bottomScriptText;
	}

	/** @return string Retrievied from HTML text */
	function printSource() {
		$url = htmlspecialchars( $this->mTitle->getFullURL() );
		return wfMsg( 'retrievedfrom', '<a href="' . $url . '">' . $url . '</a>' );
	}

	function printFooter() {
		return "<p>" .  $this->printSource() .
			"</p>\n\n<p>" . $this->pageStats() . "</p>\n";
	}

	/** overloaded by derived classes */
	function doAfterContent() {
		return '</div></div>';
	}

	function pageTitleLinks() {
		global $wgOut, $wgUser, $wgRequest, $wgLang;

		$oldid = $wgRequest->getVal( 'oldid' );
		$diff = $wgRequest->getVal( 'diff' );
		$action = $wgRequest->getText( 'action' );

		$s[] = $this->printableLink();
		$disclaimer = $this->disclaimerLink(); # may be empty

		if ( $disclaimer ) {
			$s[] = $disclaimer;
		}

		$privacy = $this->privacyLink(); # may be empty too

		if ( $privacy ) {
			$s[] = $privacy;
		}

		if ( $wgOut->isArticleRelated() ) {
			if ( $this->mTitle->getNamespace() == NS_FILE ) {
				$name = $this->mTitle->getDBkey();
				$image = wfFindFile( $this->mTitle );

				if ( $image ) {
					$link = htmlspecialchars( $image->getURL() );
					$style = $this->getInternalLinkAttributes( $link, $name );
					$s[] = "<a href=\"{$link}\"{$style}>{$name}</a>";
				}
			}
		}

		if ( 'history' == $action || isset( $diff ) || isset( $oldid ) ) {
			$s[] .= $this->link(
					$this->mTitle,
					wfMsg( 'currentrev' ),
					array(),
					array(),
					array( 'known', 'noclasses' )
			);
		}

		if ( $wgUser->getNewtalk() ) {
			# do not show "You have new messages" text when we are viewing our
			# own talk page
			if ( !$this->mTitle->equals( $wgUser->getTalkPage() ) ) {
				$tl = $this->link(
					$wgUser->getTalkPage(),
					wfMsgHtml( 'newmessageslink' ),
					array(),
					array( 'redirect' => 'no' ),
					array( 'known', 'noclasses' )
				);

				$dl = $this->link(
					$wgUser->getTalkPage(),
					wfMsgHtml( 'newmessagesdifflink' ),
					array(),
					array( 'diff' => 'cur' ),
					array( 'known', 'noclasses' )
				);
				$s[] = '<strong>' . wfMsg( 'youhavenewmessages', $tl, $dl ) . '</strong>';
				# disable caching
				$wgOut->setSquidMaxage( 0 );
				$wgOut->enableClientCache( false );
			}
		}

		$undelete = $this->getUndeleteLink();

		if ( !empty( $undelete ) ) {
			$s[] = $undelete;
		}

		return $wgLang->pipeList( $s );
	}

	function getUndeleteLink() {
		global $wgUser, $wgLang, $wgRequest;

		$action = $wgRequest->getVal( 'action', 'view' );

		if ( $wgUser->isAllowed( 'deletedhistory' ) &&
			( $this->mTitle->getArticleId() == 0 || $action == 'history' ) ) {
			$n = $this->mTitle->isDeleted();

			if ( $n ) {
				if ( $wgUser->isAllowed( 'undelete' ) ) {
					$msg = 'thisisdeleted';
				} else {
					$msg = 'viewdeleted';
				}

				return wfMsg(
					$msg,
					$this->link(
						SpecialPage::getTitleFor( 'Undelete', $this->mTitle->getPrefixedDBkey() ),
						wfMsgExt( 'restorelink', array( 'parsemag', 'escape' ), $wgLang->formatNum( $n ) ),
						array(),
						array(),
						array( 'known', 'noclasses' )
					)
				);
			}
		}

		return '';
	}

	function printableLink() {
		global $wgOut, $wgFeedClasses, $wgRequest, $wgLang;

		$s = array();

		if ( !$wgOut->isPrintable() ) {
			$printurl = $wgRequest->escapeAppendQuery( 'printable=yes' );
			$s[] = "<a href=\"$printurl\" rel=\"alternate\">" . wfMsg( 'printableversion' ) . '</a>';
		}

		if ( $wgOut->isSyndicated() ) {
			foreach ( $wgFeedClasses as $format => $class ) {
				$feedurl = $wgRequest->escapeAppendQuery( "feed=$format" );
				$s[] = "<a href=\"$feedurl\" rel=\"alternate\" type=\"application/{$format}+xml\""
						. " class=\"feedlink\">" . wfMsgHtml( "feed-$format" ) . "</a>";
			}
		}
		return $wgLang->pipeList( $s );
	}

	/**
	 * Gets the h1 element with the page title.
	 * @return string
	 */
	function pageTitle() {
		global $wgOut;
		$s = '<h1 class="pagetitle">' . $wgOut->getPageTitle() . '</h1>';
		return $s;
	}

	function pageSubtitle() {
		global $wgOut;

		$sub = $wgOut->getSubtitle();

		if ( $sub == '' ) {
			global $wgExtraSubtitle;
			$sub = wfMsgExt( 'tagline', 'parsemag' ) . $wgExtraSubtitle;
		}

		$subpages = $this->subPageSubtitle();
		$sub .= !empty( $subpages ) ? "</p><p class='subpages'>$subpages" : '';
		$s = "<p class='subtitle'>{$sub}</p>\n";

		return $s;
	}

	function subPageSubtitle() {
		$subpages = '';

		if ( !wfRunHooks( 'SkinSubPageSubtitle', array( &$subpages, $this ) ) ) {
			return $subpages;
		}

		global $wgOut;

		if ( $wgOut->isArticle() && MWNamespace::hasSubpages( $this->mTitle->getNamespace() ) ) {
			$ptext = $this->mTitle->getPrefixedText();
			if ( preg_match( '/\//', $ptext ) ) {
				$links = explode( '/', $ptext );
				array_pop( $links );
				$c = 0;
				$growinglink = '';
				$display = '';

				foreach ( $links as $link ) {
					$growinglink .= $link;
					$display .= $link;
					$linkObj = Title::newFromText( $growinglink );

					if ( is_object( $linkObj ) && $linkObj->exists() ) {
						$getlink = $this->link(
							$linkObj,
							htmlspecialchars( $display ),
							array(),
							array(),
							array( 'known', 'noclasses' )
						);

						$c++;

						if ( $c > 1 ) {
							$subpages .= wfMsgExt( 'pipe-separator', 'escapenoentities' );
						} else  {
							$subpages .= '&lt; ';
						}

						$subpages .= $getlink;
						$display = '';
					} else {
						$display .= '/';
					}
					$growinglink .= '/';
				}
			}
		}

		return $subpages;
	}

	/**
	 * Returns true if the IP should be shown in the header
	 */
	function showIPinHeader() {
		global $wgShowIPinHeader;
		return $wgShowIPinHeader && session_id() != '';
	}

	function nameAndLogin() {
		global $wgUser, $wgLang, $wgContLang;

		$logoutPage = $wgContLang->specialPage( 'Userlogout' );

		$ret = '';

		if ( $wgUser->isAnon() ) {
			if ( $this->showIPinHeader() ) {
				$name = wfGetIP();

				$talkLink = $this->link( $wgUser->getTalkPage(),
					$wgLang->getNsText( NS_TALK ) );

				$ret .= "$name ($talkLink)";
			} else {
				$ret .= wfMsg( 'notloggedin' );
			}

			$returnTo = $this->mTitle->getPrefixedDBkey();
			$query = array();

			if ( $logoutPage != $returnTo ) {
				$query['returnto'] = $returnTo;
			}

			$loginlink = $wgUser->isAllowed( 'createaccount' )
				? 'nav-login-createaccount'
				: 'login';
			$ret .= "\n<br />" . $this->link(
				SpecialPage::getTitleFor( 'Userlogin' ),
				wfMsg( $loginlink ), array(), $query
			);
		} else {
			$returnTo = $this->mTitle->getPrefixedDBkey();
			$talkLink = $this->link( $wgUser->getTalkPage(),
				$wgLang->getNsText( NS_TALK ) );

			$ret .= $this->link( $wgUser->getUserPage(),
				htmlspecialchars( $wgUser->getName() ) );
			$ret .= " ($talkLink)<br />";
			$ret .= $wgLang->pipeList( array(
				$this->link(
					SpecialPage::getTitleFor( 'Userlogout' ), wfMsg( 'logout' ),
					array(), array( 'returnto' => $returnTo )
				),
				$this->specialLink( 'Preferences' ),
			) );
		}

		$ret = $wgLang->pipeList( array(
			$ret,
			$this->link(
				Title::newFromText( wfMsgForContent( 'helppage' ) ),
				wfMsg( 'help' )
			),
		) );

		return $ret;
	}

	function getSearchLink() {
		$searchPage = SpecialPage::getTitleFor( 'Search' );
		return $searchPage->getLocalURL();
	}

	function escapeSearchLink() {
		return htmlspecialchars( $this->getSearchLink() );
	}

	function searchForm() {
		global $wgRequest, $wgUseTwoButtonsSearchForm;

		$search = $wgRequest->getText( 'search' );

		$s = '<form id="searchform' . $this->searchboxes . '" name="search" class="inline" method="post" action="'
		  . $this->escapeSearchLink() . "\">\n"
		  . '<input type="text" id="searchInput' . $this->searchboxes . '" name="search" size="19" value="'
		  . htmlspecialchars( substr( $search, 0, 256 ) ) . "\" />\n"
		  . '<input type="submit" name="go" value="' . wfMsg( 'searcharticle' ) . '" />';

		if ( $wgUseTwoButtonsSearchForm ) {
			$s .= '&#160;<input type="submit" name="fulltext" value="' . wfMsg( 'searchbutton' ) . "\" />\n";
		} else {
			$s .= ' <a href="' . $this->escapeSearchLink() . '" rel="search">' . wfMsg( 'powersearch-legend' ) . "</a>\n";
		}

		$s .= '</form>';

		// Ensure unique id's for search boxes made after the first
		$this->searchboxes = $this->searchboxes == '' ? 2 : $this->searchboxes + 1;

		return $s;
	}

	function topLinks() {
		global $wgOut;

		$s = array(
			$this->mainPageLink(),
			$this->specialLink( 'Recentchanges' )
		);

		if ( $wgOut->isArticleRelated() ) {
			$s[] = $this->editThisPage();
			$s[] = $this->historyLink();
		}

		# Many people don't like this dropdown box
		# $s[] = $this->specialPagesList();

		if ( $this->variantLinks() ) {
			$s[] = $this->variantLinks();
		}

		if ( $this->extensionTabLinks() ) {
			$s[] = $this->extensionTabLinks();
		}

		// @todo FIXME: Is using Language::pipeList impossible here? Do not quite understand the use of the newline
		return implode( $s, wfMsgExt( 'pipe-separator', 'escapenoentities' ) . "\n" );
	}

	/**
	 * Compatibility for extensions adding functionality through tabs.
	 * Eventually these old skins should be replaced with SkinTemplate-based
	 * versions, sigh...
	 * @return string
	 */
	function extensionTabLinks() {
		$tabs = array();
		$out = '';
		$s = array();
		wfRunHooks( 'SkinTemplateTabs', array( $this, &$tabs ) );
		foreach ( $tabs as $tab ) {
			$s[] = Xml::element( 'a',
				array( 'href' => $tab['href'] ),
				$tab['text'] );
		}

		if ( count( $s ) ) {
			global $wgLang;

			$out = wfMsgExt( 'pipe-separator' , 'escapenoentities' );
			$out .= $wgLang->pipeList( $s );
		}

		return $out;
	}

	/**
	 * Language/charset variant links for classic-style skins
	 * @return string
	 */
	function variantLinks() {
		$s = '';

		/* show links to different language variants */
		global $wgDisableLangConversion, $wgLang, $wgContLang;

		$variants = $wgContLang->getVariants();

		if ( !$wgDisableLangConversion && sizeof( $variants ) > 1 ) {
			foreach ( $variants as $code ) {
				$varname = $wgContLang->getVariantname( $code );

				if ( $varname == 'disable' ) {
					continue;
				}
				$s = $wgLang->pipeList( array(
					$s,
					'<a href="' . $this->mTitle->escapeLocalURL( 'variant=' . $code ) . '">' . htmlspecialchars( $varname ) . '</a>'
				) );
			}
		}

		return $s;
	}

	function bottomLinks() {
		global $wgOut, $wgUser, $wgUseTrackbacks;
		$sep = wfMsgExt( 'pipe-separator', 'escapenoentities' ) . "\n";

		$s = '';
		if ( $wgOut->isArticleRelated() ) {
			$element[] = '<strong>' . $this->editThisPage() . '</strong>';

			if ( $wgUser->isLoggedIn() ) {
				$element[] = $this->watchThisPage();
			}

			$element[] = $this->talkLink();
			$element[] = $this->historyLink();
			$element[] = $this->whatLinksHere();
			$element[] = $this->watchPageLinksLink();

			if ( $wgUseTrackbacks ) {
				$element[] = $this->trackbackLink();
			}

			if (
				$this->mTitle->getNamespace() == NS_USER ||
				$this->mTitle->getNamespace() == NS_USER_TALK
			) {
				$id = User::idFromName( $this->mTitle->getText() );
				$ip = User::isIP( $this->mTitle->getText() );

				# Both anons and non-anons have contributions list
				if ( $id || $ip ) {
					$element[] = $this->userContribsLink();
				}

				if ( $this->showEmailUser( $id ) ) {
					$element[] = $this->emailUserLink();
				}
			}

			$s = implode( $element, $sep );

			if ( $this->mTitle->getArticleId() ) {
				$s .= "\n<br />";

				// Delete/protect/move links for privileged users
				if ( $wgUser->isAllowed( 'delete' ) ) {
					$s .= $this->deleteThisPage();
				}

				if ( $wgUser->isAllowed( 'protect' ) ) {
					$s .= $sep . $this->protectThisPage();
				}

				if ( $wgUser->isAllowed( 'move' ) ) {
					$s .= $sep . $this->moveThisPage();
				}
			}

			$s .= "<br />\n" . $this->otherLanguages();
		}

		return $s;
	}

	function pageStats() {
		global $wgOut, $wgLang, $wgArticle, $wgRequest, $wgUser;
		global $wgDisableCounters, $wgMaxCredits, $wgShowCreditsIfMax, $wgPageShowWatchingUsers;

		$oldid = $wgRequest->getVal( 'oldid' );
		$diff = $wgRequest->getVal( 'diff' );

		if ( !$wgOut->isArticle() ) {
			return '';
		}

		if ( !$wgArticle instanceof Article ) {
			return '';
		}

		if ( isset( $oldid ) || isset( $diff ) ) {
			return '';
		}

		if ( 0 == $wgArticle->getID() ) {
			return '';
		}

		$s = '';

		if ( !$wgDisableCounters ) {
			$count = $wgLang->formatNum( $wgArticle->getCount() );

			if ( $count ) {
				$s = wfMsgExt( 'viewcount', array( 'parseinline' ), $count );
			}
		}

		if ( $wgMaxCredits != 0 ) {
			$s .= ' ' . Credits::getCredits( $wgArticle, $wgMaxCredits, $wgShowCreditsIfMax );
		} else {
			$s .= $this->lastModified();
		}

		if ( $wgPageShowWatchingUsers && $wgUser->getOption( 'shownumberswatching' ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'watchlist',
				array( 'COUNT(*) AS n' ),
				array(
					'wl_title' => $dbr->strencode( $this->mTitle->getDBkey() ),
					'wl_namespace' => $this->mTitle->getNamespace()
				),
				__METHOD__
			);
			$x = $dbr->fetchObject( $res );

			$s .= ' ' . wfMsgExt( 'number_of_watching_users_pageview',
				array( 'parseinline' ), $wgLang->formatNum( $x->n )
			);
		}

		return $s . ' ' .  $this->getCopyright();
	}

	function getCopyright( $type = 'detect' ) {
		global $wgRightsPage, $wgRightsUrl, $wgRightsText, $wgRequest, $wgArticle;

		if ( $type == 'detect' ) {
			$diff = $wgRequest->getVal( 'diff' );
			$isCur = $wgArticle && $wgArticle->isCurrent();

			if ( is_null( $diff ) && !$isCur && wfMsgForContent( 'history_copyright' ) !== '-' ) {
				$type = 'history';
			} else {
				$type = 'normal';
			}
		}

		if ( $type == 'history' ) {
			$msg = 'history_copyright';
		} else {
			$msg = 'copyright';
		}

		$out = '';

		if ( $wgRightsPage ) {
			$title = Title::newFromText( $wgRightsPage );
			$link = $this->linkKnown( $title, $wgRightsText );
		} elseif ( $wgRightsUrl ) {
			$link = $this->makeExternalLink( $wgRightsUrl, $wgRightsText );
		} elseif ( $wgRightsText ) {
			$link = $wgRightsText;
		} else {
			# Give up now
			return $out;
		}

		// Allow for site and per-namespace customization of copyright notice.
		$forContent = true;

		if ( isset( $wgArticle ) ) {
			wfRunHooks( 'SkinCopyrightFooter', array( $wgArticle->getTitle(), $type, &$msg, &$link, &$forContent ) );
		}

		if ( $forContent ) {
			$out .= wfMsgForContent( $msg, $link );
		} else {
			$out .= wfMsg( $msg, $link );
		}

		return $out;
	}

	function getCopyrightIcon() {
		global $wgRightsUrl, $wgRightsText, $wgRightsIcon, $wgCopyrightIcon;

		$out = '';

		if ( isset( $wgCopyrightIcon ) && $wgCopyrightIcon ) {
			$out = $wgCopyrightIcon;
		} elseif ( $wgRightsIcon ) {
			$icon = htmlspecialchars( $wgRightsIcon );

			if ( $wgRightsUrl ) {
				$url = htmlspecialchars( $wgRightsUrl );
				$out .= '<a href="' . $url . '">';
			}

			$text = htmlspecialchars( $wgRightsText );
			$out .= "<img src=\"$icon\" alt=\"$text\" width=\"88\" height=\"31\" />";

			if ( $wgRightsUrl ) {
				$out .= '</a>';
			}
		}

		return $out;
	}

	/**
	 * Gets the powered by MediaWiki icon.
	 * @return string
	 */
	function getPoweredBy() {
		global $wgStylePath;

		$url = htmlspecialchars( "$wgStylePath/common/images/poweredby_mediawiki_88x31.png" );
		$text = '<a href="http://www.mediawiki.org/"><img src="' . $url . '" height="31" width="88" alt="Powered by MediaWiki" /></a>';
		wfRunHooks( 'SkinGetPoweredBy', array( &$text, $this ) );	
		return $text;
	}

	function lastModified() {
		global $wgLang, $wgArticle;

		if ( $this->mRevisionId && $this->mRevisionId != $wgArticle->getLatest() ) {
			$timestamp = Revision::getTimestampFromId( $wgArticle->getTitle(), $this->mRevisionId );
		} else {
			$timestamp = $wgArticle->getTimestamp();
		}

		if ( $timestamp ) {
			$d = $wgLang->date( $timestamp, true );
			$t = $wgLang->time( $timestamp, true );
			$s = ' ' . wfMsg( 'lastmodifiedat', $d, $t );
		} else {
			$s = '';
		}

		if ( wfGetLB()->getLaggedSlaveMode() ) {
			$s .= ' <strong>' . wfMsg( 'laggedslavemode' ) . '</strong>';
		}

		return $s;
	}

	function logoText( $align = '' ) {
		if ( $align != '' ) {
			$a = " align='{$align}'";
		} else {
			$a = '';
		}

		$mp = wfMsg( 'mainpage' );
		$mptitle = Title::newMainPage();
		$url = ( is_object( $mptitle ) ? $mptitle->escapeLocalURL() : '' );

		$logourl = $this->getLogo();
		$s = "<a href='{$url}'><img{$a} src='{$logourl}' alt='[{$mp}]' /></a>";

		return $s;
	}

	/**
	 * Show a drop-down box of special pages
	 */
	function specialPagesList() {
		global $wgContLang, $wgServer, $wgRedirectScript;

		$pages = array_merge( SpecialPage::getRegularPages(), SpecialPage::getRestrictedPages() );

		foreach ( $pages as $name => $page ) {
			$pages[$name] = $page->getDescription();
		}

		$go = wfMsg( 'go' );
		$sp = wfMsg( 'specialpages' );
		$spp = $wgContLang->specialPage( 'Specialpages' );

		$s = '<form id="specialpages" method="get" ' .
		  'action="' . htmlspecialchars( "{$wgServer}{$wgRedirectScript}" ) . "\">\n";
		$s .= "<select name=\"wpDropdown\">\n";
		$s .= "<option value=\"{$spp}\">{$sp}</option>\n";


		foreach ( $pages as $name => $desc ) {
			$p = $wgContLang->specialPage( $name );
			$s .= "<option value=\"{$p}\">{$desc}</option>\n";
		}

		$s .= "</select>\n";
		$s .= "<input type='submit' value=\"{$go}\" name='redirect' />\n";
		$s .= "</form>\n";

		return $s;
	}

	/**
	 * Renders a $wgFooterIcons icon acording to the method's arguments
	 * @param $icon Array: The icon to build the html for, see $wgFooterIcons for the format of this array
	 * @param $withImage Boolean: Whether to use the icon's image or output a text-only footericon
	 */
	function makeFooterIcon( $icon, $withImage = 'withImage' ) {
		if ( is_string( $icon ) ) {
			$html = $icon;
		} else { // Assuming array
			$url = isset($icon["url"]) ? $icon["url"] : null;
			unset( $icon["url"] );
			if ( isset( $icon["src"] ) && $withImage === 'withImage' ) {
				$html = Html::element( 'img', $icon ); // do this the lazy way, just pass icon data as an attribute array
			} else {
				$html = htmlspecialchars( $icon["alt"] );
			}
			if ( $url ) {
				$html = Html::rawElement( 'a', array( "href" => $url ), $html );
			}
		}
		return $html;
	}

	/**
	 * Gets the link to the wiki's main page.
	 * @return string
	 */
	function mainPageLink() {
		$s = $this->link(
			Title::newMainPage(),
			wfMsg( 'mainpage' ),
			array(),
			array(),
			array( 'known', 'noclasses' )
		);

		return $s;
	}

	public function footerLink( $desc, $page ) {
		// if the link description has been set to "-" in the default language,
		if ( wfMsgForContent( $desc )  == '-' ) {
			// then it is disabled, for all languages.
			return '';
		} else {
			// Otherwise, we display the link for the user, described in their
			// language (which may or may not be the same as the default language),
			// but we make the link target be the one site-wide page.
			$title = Title::newFromText( wfMsgForContent( $page ) );

			return $this->linkKnown(
				$title,
				wfMsgExt( $desc, array( 'parsemag', 'escapenoentities' ) )
			);
		}
	}

	/**
	 * Gets the link to the wiki's privacy policy page.
	 */
	function privacyLink() {
		return $this->footerLink( 'privacy', 'privacypage' );
	}

	/**
	 * Gets the link to the wiki's about page.
	 */
	function aboutLink() {
		return $this->footerLink( 'aboutsite', 'aboutpage' );
	}

	/**
	 * Gets the link to the wiki's general disclaimers page.
	 */
	function disclaimerLink() {
		return $this->footerLink( 'disclaimers', 'disclaimerpage' );
	}

	function editThisPage() {
		global $wgOut;

		if ( !$wgOut->isArticleRelated() ) {
			$s = wfMsg( 'protectedpage' );
		} else {
			if ( $this->mTitle->quickUserCan( 'edit' ) && $this->mTitle->exists() ) {
				$t = wfMsg( 'editthispage' );
			} elseif ( $this->mTitle->quickUserCan( 'create' ) && !$this->mTitle->exists() ) {
				$t = wfMsg( 'create-this-page' );
			} else {
				$t = wfMsg( 'viewsource' );
			}

			$s = $this->link(
				$this->mTitle,
				$t,
				array(),
				$this->editUrlOptions(),
				array( 'known', 'noclasses' )
			);
		}

		return $s;
	}

	/**
	 * Return URL options for the 'edit page' link.
	 * This may include an 'oldid' specifier, if the current page view is such.
	 *
	 * @return array
	 * @private
	 */
	function editUrlOptions() {
		global $wgArticle;

		$options = array( 'action' => 'edit' );

		if ( $this->mRevisionId && ! $wgArticle->isCurrent() ) {
			$options['oldid'] = intval( $this->mRevisionId );
		}

		return $options;
	}

	function deleteThisPage() {
		global $wgUser, $wgRequest;

		$diff = $wgRequest->getVal( 'diff' );

		if ( $this->mTitle->getArticleId() && ( !$diff ) && $wgUser->isAllowed( 'delete' ) ) {
			$t = wfMsg( 'deletethispage' );

			$s = $this->link(
				$this->mTitle,
				$t,
				array(),
				array( 'action' => 'delete' ),
				array( 'known', 'noclasses' )
			);
		} else {
			$s = '';
		}

		return $s;
	}

	function protectThisPage() {
		global $wgUser, $wgRequest;

		$diff = $wgRequest->getVal( 'diff' );

		if ( $this->mTitle->getArticleId() && ( ! $diff ) && $wgUser->isAllowed( 'protect' ) ) {
			if ( $this->mTitle->isProtected() ) {
				$text = wfMsg( 'unprotectthispage' );
				$query = array( 'action' => 'unprotect' );
			} else {
				$text = wfMsg( 'protectthispage' );
				$query = array( 'action' => 'protect' );
			}

			$s = $this->link(
				$this->mTitle,
				$text,
				array(),
				$query,
				array( 'known', 'noclasses' )
			);
		} else {
			$s = '';
		}

		return $s;
	}

	function watchThisPage() {
		global $wgOut;
		++$this->mWatchLinkNum;

		if ( $wgOut->isArticleRelated() ) {
			if ( $this->mTitle->userIsWatching() ) {
				$text = wfMsg( 'unwatchthispage' );
				$query = array( 'action' => 'unwatch' );
				$id = 'mw-unwatch-link' . $this->mWatchLinkNum;
			} else {
				$text = wfMsg( 'watchthispage' );
				$query = array( 'action' => 'watch' );
				$id = 'mw-watch-link' . $this->mWatchLinkNum;
			}

			$s = $this->link(
				$this->mTitle,
				$text,
				array( 'id' => $id ),
				$query,
				array( 'known', 'noclasses' )
			);
		} else {
			$s = wfMsg( 'notanarticle' );
		}

		return $s;
	}

	function moveThisPage() {
		if ( $this->mTitle->quickUserCan( 'move' ) ) {
			return $this->link(
				SpecialPage::getTitleFor( 'Movepage' ),
				wfMsg( 'movethispage' ),
				array(),
				array( 'target' => $this->mTitle->getPrefixedDBkey() ),
				array( 'known', 'noclasses' )
			);
		} else {
			// no message if page is protected - would be redundant
			return '';
		}
	}

	function historyLink() {
		return $this->link(
			$this->mTitle,
			wfMsgHtml( 'history' ),
			array( 'rel' => 'archives' ),
			array( 'action' => 'history' )
		);
	}

	function whatLinksHere() {
		return $this->link(
			SpecialPage::getTitleFor( 'Whatlinkshere', $this->mTitle->getPrefixedDBkey() ),
			wfMsgHtml( 'whatlinkshere' ),
			array(),
			array(),
			array( 'known', 'noclasses' )
		);
	}

	function userContribsLink() {
		return $this->link(
			SpecialPage::getTitleFor( 'Contributions', $this->mTitle->getDBkey() ),
			wfMsgHtml( 'contributions' ),
			array(),
			array(),
			array( 'known', 'noclasses' )
		);
	}

	function showEmailUser( $id ) {
		global $wgUser;
		$targetUser = User::newFromId( $id );
		return $wgUser->canSendEmail() && # the sending user must have a confirmed email address
			$targetUser->canReceiveEmail(); # the target user must have a confirmed email address and allow emails from users
	}

	function emailUserLink() {
		return $this->link(
			SpecialPage::getTitleFor( 'Emailuser', $this->mTitle->getDBkey() ),
			wfMsg( 'emailuser' ),
			array(),
			array(),
			array( 'known', 'noclasses' )
		);
	}

	function watchPageLinksLink() {
		global $wgOut;

		if ( !$wgOut->isArticleRelated() ) {
			return '(' . wfMsg( 'notanarticle' ) . ')';
		} else {
			return $this->link(
				SpecialPage::getTitleFor( 'Recentchangeslinked', $this->mTitle->getPrefixedDBkey() ),
				wfMsg( 'recentchangeslinked-toolbox' ),
				array(),
				array(),
				array( 'known', 'noclasses' )
			);
		}
	}

	function trackbackLink() {
		return '<a href="' . $this->mTitle->trackbackURL() . '">'
			. wfMsg( 'trackbacklink' ) . '</a>';
	}

	function otherLanguages() {
		global $wgOut, $wgContLang, $wgHideInterlanguageLinks;

		if ( $wgHideInterlanguageLinks ) {
			return '';
		}

		$a = $wgOut->getLanguageLinks();

		if ( 0 == count( $a ) ) {
			return '';
		}

		$s = wfMsg( 'otherlanguages' ) . wfMsg( 'colon-separator' );
		$first = true;

		if ( $wgContLang->isRTL() ) {
			$s .= '<span dir="LTR">';
		}

		foreach ( $a as $l ) {
			if ( !$first ) {
				$s .= wfMsgExt( 'pipe-separator', 'escapenoentities' );
			}

			$first = false;

			$nt = Title::newFromText( $l );
			$url = $nt->escapeFullURL();
			$text = $wgContLang->getLanguageName( $nt->getInterwiki() );
			$title = htmlspecialchars( $nt->getText() );

			if ( $text == '' ) {
				$text = $l;
			}

			$style = $this->getExternalLinkAttributes();
			$s .= "<a href=\"{$url}\" title=\"{$title}\"{$style}>{$text}</a>";
		}

		if ( $wgContLang->isRTL() ) {
			$s .= '</span>';
		}

		return $s;
	}

	function talkLink() {
		if ( NS_SPECIAL == $this->mTitle->getNamespace() ) {
			# No discussion links for special pages
			return '';
		}

		$linkOptions = array();

		if ( $this->mTitle->isTalkPage() ) {
			$link = $this->mTitle->getSubjectPage();
			switch( $link->getNamespace() ) {
				case NS_MAIN:
					$text = wfMsg( 'articlepage' );
					break;
				case NS_USER:
					$text = wfMsg( 'userpage' );
					break;
				case NS_PROJECT:
					$text = wfMsg( 'projectpage' );
					break;
				case NS_FILE:
					$text = wfMsg( 'imagepage' );
					# Make link known if image exists, even if the desc. page doesn't.
					if ( wfFindFile( $link ) )
						$linkOptions[] = 'known';
					break;
				case NS_MEDIAWIKI:
					$text = wfMsg( 'mediawikipage' );
					break;
				case NS_TEMPLATE:
					$text = wfMsg( 'templatepage' );
					break;
				case NS_HELP:
					$text = wfMsg( 'viewhelppage' );
					break;
				case NS_CATEGORY:
					$text = wfMsg( 'categorypage' );
					break;
				default:
					$text = wfMsg( 'articlepage' );
			}
		} else {
			$link = $this->mTitle->getTalkPage();
			$text = wfMsg( 'talkpage' );
		}

		$s = $this->link( $link, $text, array(), array(), $linkOptions );

		return $s;
	}

	function commentLink() {
		global $wgOut;

		if ( $this->mTitle->getNamespace() == NS_SPECIAL ) {
			return '';
		}

		# __NEWSECTIONLINK___ changes behaviour here
		# If it is present, the link points to this page, otherwise
		# it points to the talk page
		if ( $this->mTitle->isTalkPage() ) {
			$title = $this->mTitle;
		} elseif ( $wgOut->showNewSectionLink() ) {
			$title = $this->mTitle;
		} else {
			$title = $this->mTitle->getTalkPage();
		}

		return $this->link(
			$title,
			wfMsg( 'postcomment' ),
			array(),
			array(
				'action' => 'edit',
				'section' => 'new'
			),
			array( 'known', 'noclasses' )
		);
	}

	function getUploadLink() {
		global $wgUploadNavigationUrl;

		if ( $wgUploadNavigationUrl ) {
			# Using an empty class attribute to avoid automatic setting of "external" class
			return $this->makeExternalLink( $wgUploadNavigationUrl, wfMsgHtml( 'upload' ), false, null, array( 'class' => '' ) );
		} else {
			return $this->link(
				SpecialPage::getTitleFor( 'Upload' ),
				wfMsgHtml( 'upload' ),
				array(),
				array(),
				array( 'known', 'noclasses' )
			);
		}
	}

	/**
	 * Return a fully resolved style path url to images or styles stored in the common folder.
	 * This method returns a url resolved using the configured skin style path
	 * and includes the style version inside of the url.
	 * @param $name String: The name or path of the common file to return the full path for.
	 * @return String The fully resolved style path url including styleversion
	 */
	function getCommonStylePath( $name ) {
		global $wgStylePath, $wgStyleVersion;
		return "{$wgStylePath}/common/$name?{$wgStyleVersion}";
	}

	/**
	 * Return a fully resolved style path url to images or styles stored in the curent skins's folder.
	 * This method returns a url resolved using the configured skin style path
	 * and includes the style version inside of the url.
	 * @param $name String: The name or path of the skin resource file to return the full path for.
	 * @return String The fully resolved style path url including styleversion
	 */
	function getSkinStylePath( $name ) {
		global $wgStylePath, $wgStyleVersion;
		return "{$wgStylePath}/{$this->stylename}/$name?{$wgStyleVersion}";
	}

	/* these are used extensively in SkinTemplate, but also some other places */
	static function makeMainPageUrl( $urlaction = '' ) {
		$title = Title::newMainPage();
		self::checkTitle( $title, '' );

		return $title->getLocalURL( $urlaction );
	}

	static function makeSpecialUrl( $name, $urlaction = '' ) {
		$title = SpecialPage::getTitleFor( $name );
		return $title->getLocalURL( $urlaction );
	}

	static function makeSpecialUrlSubpage( $name, $subpage, $urlaction = '' ) {
		$title = SpecialPage::getSafeTitleFor( $name, $subpage );
		return $title->getLocalURL( $urlaction );
	}

	static function makeI18nUrl( $name, $urlaction = '' ) {
		$title = Title::newFromText( wfMsgForContent( $name ) );
		self::checkTitle( $title, $name );
		return $title->getLocalURL( $urlaction );
	}

	static function makeUrl( $name, $urlaction = '' ) {
		$title = Title::newFromText( $name );
		self::checkTitle( $title, $name );

		return $title->getLocalURL( $urlaction );
	}

	/**
	 * If url string starts with http, consider as external URL, else
	 * internal
	 */
	static function makeInternalOrExternalUrl( $name ) {
		if ( preg_match( '/^(?:' . wfUrlProtocols() . ')/', $name ) ) {
			return $name;
		} else {
			return self::makeUrl( $name );
		}
	}

	# this can be passed the NS number as defined in Language.php
	static function makeNSUrl( $name, $urlaction = '', $namespace = NS_MAIN ) {
		$title = Title::makeTitleSafe( $namespace, $name );
		self::checkTitle( $title, $name );

		return $title->getLocalURL( $urlaction );
	}

	/* these return an array with the 'href' and boolean 'exists' */
	static function makeUrlDetails( $name, $urlaction = '' ) {
		$title = Title::newFromText( $name );
		self::checkTitle( $title, $name );

		return array(
			'href' => $title->getLocalURL( $urlaction ),
			'exists' => $title->getArticleID() != 0,
		);
	}

	/**
	 * Make URL details where the article exists (or at least it's convenient to think so)
	 */
	static function makeKnownUrlDetails( $name, $urlaction = '' ) {
		$title = Title::newFromText( $name );
		self::checkTitle( $title, $name );

		return array(
			'href' => $title->getLocalURL( $urlaction ),
			'exists' => true
		);
	}

	# make sure we have some title to operate on
	static function checkTitle( &$title, $name ) {
		if ( !is_object( $title ) ) {
			$title = Title::newFromText( $name );
			if ( !is_object( $title ) ) {
				$title = Title::newFromText( '--error: link target missing--' );
			}
		}
	}

	/**
	 * Build an array that represents the sidebar(s), the navigation bar among them
	 *
	 * @return array
	 */
	function buildSidebar() {
		global $parserMemc, $wgEnableSidebarCache, $wgSidebarCacheExpiry;
		global $wgLang;
		wfProfileIn( __METHOD__ );

		$key = wfMemcKey( 'sidebar', $wgLang->getCode() );

		if ( $wgEnableSidebarCache ) {
			$cachedsidebar = $parserMemc->get( $key );
			if ( $cachedsidebar ) {
				wfProfileOut( __METHOD__ );
				return $cachedsidebar;
			}
		}

		$bar = array();
		$this->addToSidebar( $bar, 'sidebar' );

		wfRunHooks( 'SkinBuildSidebar', array( $this, &$bar ) );
		if ( $wgEnableSidebarCache ) {
			$parserMemc->set( $key, $bar, $wgSidebarCacheExpiry );
		}

		wfProfileOut( __METHOD__ );
		return $bar;
	}
	/**
	 * Add content from a sidebar system message
	 * Currently only used for MediaWiki:Sidebar (but may be used by Extensions)
	 *
	 * This is just a wrapper around addToSidebarPlain() for backwards compatibility
	 *
	 * @param &$bar array
	 * @param $message String
	 */
	function addToSidebar( &$bar, $message ) {
		$this->addToSidebarPlain( $bar, wfMsgForContent( $message ) );
	}

	/**
	 * Add content from plain text
	 * @since 1.17
	 * @param &$bar array
	 * @param $text string
	 */
	function addToSidebarPlain( &$bar, $text ) {
		$lines = explode( "\n", $text );
		$wikiBar = array(); # We need to handle the wikitext on a different variable, to avoid trying to do an array operation on text, which would be a fatal error.

		$heading = '';

		foreach ( $lines as $line ) {
			if ( strpos( $line, '*' ) !== 0 ) {
				continue;
			}

			if ( strpos( $line, '**' ) !== 0 ) {
				$heading = trim( $line, '* ' );
				if ( !array_key_exists( $heading, $bar ) ) {
					$bar[$heading] = array();
				}
			} else {
				$line = trim( $line, '* ' );

				if ( strpos( $line, '|' ) !== false ) { // sanity check
					$line = array_map( 'trim', explode( '|', $line, 2 ) );
					$link = wfMsgForContent( $line[0] );

					if ( $link == '-' ) {
						continue;
					}

					$text = wfMsgExt( $line[1], 'parsemag' );

					if ( wfEmptyMsg( $line[1], $text ) ) {
						$text = $line[1];
					}

					if ( wfEmptyMsg( $line[0], $link ) ) {
						$link = $line[0];
					}

					if ( preg_match( '/^(?:' . wfUrlProtocols() . ')/', $link ) ) {
						$href = $link;
					} else {
						$title = Title::newFromText( $link );

						if ( $title ) {
							$title = $title->fixSpecialName();
							$href = $title->getLocalURL();
						} else {
							$href = 'INVALID-TITLE';
						}
					}

					$bar[$heading][] = array(
						'text' => $text,
						'href' => $href,
						'id' => 'n-' . strtr( $line[1], ' ', '-' ),
						'active' => false
					);
				} else if ( ( substr( $line, 0, 2 ) == '{{' ) && ( substr( $line, -2 ) == '}}' ) ) {
					global $wgParser, $wgTitle;

					$line = substr( $line, 2, strlen( $line ) - 4 );

					$options = new ParserOptions();
					$options->setEditSection( false );
					$options->setInterfaceMessage( true );
					$wikiBar[$heading] = $wgParser->parse( wfMsgForContentNoTrans( $line ) , $wgTitle, $options )->getText();
				} else {
					continue;
				}
			}
		}

		if ( count( $wikiBar ) > 0 ) {
			$bar = array_merge( $bar, $wikiBar );
		}

		return $bar;
	}

	/**
	 * Should we include common/wikiprintable.css?  Skins that have their own
	 * print stylesheet should override this and return false.  (This is an
	 * ugly hack to get Monobook to play nicely with
	 * OutputPage::headElement().)
	 *
	 * @return bool
	 */
	public function commonPrintStylesheet() {
		return true;
	}

	/**
	 * Gets new talk page messages for the current user.
	 * @return MediaWiki message or if no new talk page messages, nothing
	 */
	function getNewtalks() {
		global $wgUser, $wgOut;

		$newtalks = $wgUser->getNewMessageLinks();
		$ntl = '';

		if ( count( $newtalks ) == 1 && $newtalks[0]['wiki'] === wfWikiID() ) {
			$userTitle = $this->mUser->getUserPage();
			$userTalkTitle = $userTitle->getTalkPage();

			if ( !$userTalkTitle->equals( $this->mTitle ) ) {
				$newMessagesLink = $this->link(
					$userTalkTitle,
					wfMsgHtml( 'newmessageslink' ),
					array(),
					array( 'redirect' => 'no' ),
					array( 'known', 'noclasses' )
				);

				$newMessagesDiffLink = $this->link(
					$userTalkTitle,
					wfMsgHtml( 'newmessagesdifflink' ),
					array(),
					array( 'diff' => 'cur' ),
					array( 'known', 'noclasses' )
				);

				$ntl = wfMsg(
					'youhavenewmessages',
					$newMessagesLink,
					$newMessagesDiffLink
				);
				# Disable Squid cache
				$wgOut->setSquidMaxage( 0 );
			}
		} elseif ( count( $newtalks ) ) {
			// _>" " for BC <= 1.16
			$sep = str_replace( '_', ' ', wfMsgHtml( 'newtalkseparator' ) );
			$msgs = array();

			foreach ( $newtalks as $newtalk ) {
				$msgs[] = Xml::element(
					'a',
					array( 'href' => $newtalk['link'] ), $newtalk['wiki']
				);
			}
			$parts = implode( $sep, $msgs );
			$ntl = wfMsgHtml( 'youhavenewmessagesmulti', $parts );
			$wgOut->setSquidMaxage( 0 );
		}

		return $ntl;
	}
}
