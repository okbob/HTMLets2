<?php
use MediaWiki\MediaWikiServices;

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

class HTMLets2Hooks {
	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'htmlet2', [ self::class, 'wfRenderHTMLet2' ] );
	}

	/**
	 * @param string $input Dirty HTML
	 * @return Purified HTML
	 */
	public static function wfRenderHTMLet2(  $name, $argv, $parser ) {
		global $wgHTMLets2Directory;

		if ( @$argv['nocache'] ) {
			$parser->getOutput()->updateCacheExpiry( 0 );
		}

		$name = preg_replace( '@[\\\\/!]|^\.+?&#@', '', $name ); #strip path separators and leading dots.
		$name .= '.html'; #append html ending, for added security and convenience

		$f = "$wgHTMLets2Directory/$name";

		if ( !preg_match('!^\w+://!', $wgHTMLets2Directory ) && !file_exists( $f ) ) {
			$output = Html::rawElement(
				'div',
				array( 'class' => 'error' ),
				wfMessage( 'htmlets-filenotfound', $f )->inContentLanguage()->escaped()
			);
		} else {
			$output = file_get_contents( $f );
			if ( $output === false ) {
				$output = Html::rawElement(
					'div',
					array( 'class' => 'error' ),
					wfMessage( 'htmlets-loadfailed', $name )->inContentLanguage()->escaped()
				);
			}
		}

		$output = '<!-- @HTMLetsHACK@ '.base64_encode($output).' @HTMLetsHACK@ -->';

		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$hookContainer->register( 'ParserAfterTidy', [ self::class, 'wfRenderHTMLet2HackPostProcess' ] );

		return $output;
	}

	public static function wfRenderHTMLet2HackPostProcess( $parser, &$text ) {
		$text = preg_replace_callback(
			'/<!-- @HTMLetsHACK@ ([0-9a-zA-Z\\+\\/]+=*) @HTMLetsHACK@ -->/sm',
			function ($m) {
				return base64_decode("$m[1]");
			},
			$text
		);

		return true;
	}
}
