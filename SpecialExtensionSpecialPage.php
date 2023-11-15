<?php



class SpecialPageClass extends SpecialPage {
  public function __construct() {
    parent::__construct( 'DanteCategoryBrowser_SpecialPage' );   // only one parameter; second parameter would be a restriction on rights
    danteLog ("DanteCategoryBrowser", "constructed\n");
  }



	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
  danteLog ("DanteCategoryBrowser", "in execute\n");  // looks like this is never called

		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		# Get request data from, e.g.
		$param = $request->getText( 'param' );

		# Do stuff
		# ...
		$wikitext = 'Hello world!';
		$output->addWikiTextAsInterface( $wikitext );



		$out = $this->getOutput();

//		$out->setPageTitle( $this->msg( 'special-extensionSpecialPage-title' ) );
//		$out->addHelpLink( 'How to become a MediaWiki hacker' );
//		$out->addWikiMsg( 'special-extensionSpecialPage-intro' );
	}

// define the group under which the page will show up in "Special:SpecialPages
// we here use "dante". This means that in the i18n file, here or somewhere else, we have to declare a "specialpages-group-dante"
protected function getGroupName() {
  return 'dante';
  }


}





