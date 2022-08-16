<?php



namespace vertwo\plite\Web;



use vertwo\plite\Provider\ProviderFactory;
use function vertwo\plite\clog;



class VertwoTemplate
{
    static $wl_title;
    static $wl_name;
    static $wl_logo;
    static $wl_bg;
    static $wl_copyright;
    static $wl_use_powered_by;



    public static function init ( $pfPrefix = false )
    {
        $ajax = new Ajax();
        if ( false === $pfPrefix ) $pfPrefix = $ajax->testBoth("pf");
        $pfName = $pfPrefix . "ProviderFactory";

        clog("pf name", $pfName);

        /** @var ProviderFactory $pf */
        $pf = new $pfName();

        self::$wl_title          = $pf->get("wl_title");
        self::$wl_name           = $pf->get("wl_name");
        self::$wl_logo           = $pf->get("wl_logo");
        self::$wl_bg             = $pf->get("wl_bg");
        self::$wl_copyright      = $pf->get("wl_copyright_notice");
        self::$wl_use_powered_by = $pf->get("wl_using_powered_by_v2");

        clog("white-label title", self::$wl_title);
        clog("white-label name", self::$wl_name);
        clog("white-label logo", self::$wl_logo);
        clog("white-label bg", self::$wl_bg);
        clog("white-label copyright", self::$wl_copyright);
        clog("white-label use_pbv2", self::$wl_use_powered_by);
    }



    public static function getSolidFooterContents ()
    {
        $copyright = self::$wl_copyright;
        $pby       = self::$wl_use_powered_by
            ? '<p>Powered by <span class="v2">Version2</span></p>'
            : "";

        $footerContents = <<<EOF
    <div>
        $pby
    </div>
    <div>
        <p>$copyright</p>
    </div>

EOF;

        return $footerContents;
    }
}
