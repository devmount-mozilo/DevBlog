<?php

/**
 * moziloCMS Plugin: DevBlog
 *
 * A small blog plugin vor Mozilo
 *
 * PHP version 5
 *
 * @category PHP
 * @package  PHP_MoziloPlugins
 * @author   DEVMOUNT <mail@devmount.de>
 * @license  GPL v3+
 * @version  GIT: v0.x.jjjj-mm-dd
 * @link     https://github.com/devmount-mozilo/DevBlog/wiki/Dokumentation
 * @see      Verse
 *           – The Bible
 *
 * Plugin created by DEVMOUNT
 * www.devmount.de
 *
 */

// only allow moziloCMS environment
if (!defined('IS_CMS')) {
    die();
}

/**
 * DevBlog Class
 *
 * @category PHP
 * @package  PHP_MoziloPlugins
 * @author   DEVMOUNT <mail@devmount.de>
 * @license  GPL v3+
 * @link     https://github.com/devmount-mozilo/DevBlog
 */
class DevBlog extends Plugin
{
    // language
    private $_admin_lang;
    private $_cms_lang;

    // plugin information
    const PLUGIN_AUTHOR  = 'DEVMOUNT';
    const PLUGIN_TITLE   = 'DevBlog';
    const PLUGIN_VERSION = 'v0.x.jjjj-mm-dd';
    const MOZILO_VERSION = '2.0';
    const PLUGIN_DOCU
        = 'https://github.com/devmount-mozilo/DevBlog/wiki/Dokumentation';

    private $_plugin_tags = array(
        'tag1' => '{DevBlog|meta|<tags>}',
        'tag2' => '{DevBlog|list|articles}',
        'tag3' => '{DevBlog|list|categories}',
        'tag4' => '{DevBlog|list|authors}',
        'tag5' => '{DevBlog|list|years}',
    );

    const LOGO_URL = 'http://media.devmount.de/logo_pluginconf.png';

    /**
     * set configuration elements, their default values and their configuration
     * parameters
     *
     * @var array $_confdefault
     *      text     => default, type, maxlength, size, regex
     *      textarea => default, type, cols, rows, regex
     *      password => default, type, maxlength, size, regex, saveasmd5
     *      check    => default, type
     *      radio    => default, type, descriptions
     *      select   => default, type, descriptions, multiselect
     */
    private $_confdefault = array(
        'cat' => array(
            'Blog',
            'text',
            '100',
            '5',
            "",
        ),
        'page' => array(
            'Blog',
            'text',
            '100',
            '5',
            "",
        ),
        'divider' => array(
            '—',
            'text',
            '100',
            '5',
            "",
        ),
        'date_format_teaser' => array(
            'd.m.Y',
            'text',
            '100',
            '5',
            "",
        ),
        'date_format_article' => array(
            'd.m.Y, H:i',
            'text',
            '100',
            '5',
            "",
        ),
        'template_article_teaser' => array(
            '#date# #datetime# #author# #category# #title# #teaser# #divider# #urlarticle#',
            'textarea',
            '100',
            '10',
            "",
        ),
        'template_article_header' => array(
            '#date# #datetime# #author# #category# #title# #teaser# #divider# #urlarticle#',
            'textarea',
            '100',
            '10',
            "",
        ),
    );

    /**
     * meta data each article has
     *
     * @var array
     */
    private $_metavalues = array(
        'date',
        'time',
        'author',
        'category',
        'title',
        'teaser',
    );

    /**
     * template marker
     *
     * @var array
     */
    private $_marker = array(
        '#date#',
        '#datetime#',
        '#author#',
        '#category#',
        '#title#',
        '#teaser#',
        '#divider#',
        '#urlarticle#',
    );

    /**
     * creates plugin content
     *
     * @param string $value Parameter divided by '|'
     *
     * @return string HTML output
     */
    function getContent($value)
    {
        // get params
        list($mode, $submode) = array_map(trim, explode('|', $value));

        // handle given mode: call return function
        switch ($mode) {
        case 'meta':
            return $this->getArticleHeader($submode);
            break;
        case 'list':
            switch ($submode) {
            case 'articles':
                return $this->listArticles();
                break;
            case 'categories':
                return $this->listAttribute('category');
                break;
            case 'authors':
                return $this->listAttribute('author');
                break;
            case 'years':
                return $this->listAttribute('date');
                break;
            default:
                return $this->noMode();
                break;
            }
            break;
        default:
            return $this->noMode();
            break;
        }

        return $this->noMode();
    }

    /**
     * returns output for article header meta data
     *
     * @param  string $tags line break separated meta tags
     *
     * @return string       html
     */
    private function getArticleHeader($tags)
    {
        global $CatPage;

        // get lines
        $meta = array_slice(array_map("trim", explode("-html_br~", $tags)), 1, count($this->_metavalues));
        $article = array_combine($this->_metavalues, $meta);

        // initialize return content, begin plugin content
        $content = '<!-- BEGIN ' . self::PLUGIN_TITLE . ' plugin content -->';

        $date = $article['date'] . ' ' . $article['time'];
        $urlcategory = $CatPage->get_Href(
            $this->settings->get('cat'),
            $this->settings->get('page'),
            'c=' . $article['category']
        );
        $urlarticle = $CatPage->get_Href($this->settings->get('cat'), PAGE_REQUEST);
        // remove line breaks from template
        $template = str_replace('<br />', '', $this->settings->get('template_article_header'));;
        // fill template markers with content
        $template = str_replace(
            $this->_marker,
            array(
                date_format(date_create($date), $this->settings->get('date_format_article')),
                $date,
                $article['author'],
                '<a href="' . $urlcategory . '">' . $article['category'] . '</a>',
                $article['title'],
                $article['teaser'],
                $this->settings->get('divider'),
                $urlarticle,
            ),
            $template
        );
        $content .= $template;

        // end plugin content
        $content .= '<!-- END ' . self::PLUGIN_TITLE . ' plugin content -->';

        return $content;
    }

    /**
     * returns a list of article teasers
     *
     * @return string            html
     */
    private function listArticles()
    {
        global $CatPage;

        $number = getRequestValue('n');
        $category = getRequestValue('c');
        $author = getRequestValue('a');
        $year = getRequestValue('d');

        // get all articles
        $articles = $this->getArticles();

        // initialize return content, begin plugin content
        $content = '<!-- BEGIN ' . self::PLUGIN_TITLE . ' plugin content -->';

        // check category request
        if ($category) {
            foreach ($articles as $key => $article) {
                if ($article['category'] != $category) {
                    unset($articles[$key]);
                }
            }
            $content .= '<h2>' . $category . '</h2>';
            $content .= '<p>' . $this->countArticles('category', $category) . ' Artikel</p>';
        }
        // check author request
        if ($author) {
            foreach ($articles as $key => $article) {
                if ($article['author'] != $author) {
                    unset($articles[$key]);
                }
            }
            $content .= '<h2>' . $author . '</h2>';
            $content .= '<p>' . $this->countArticles('author', $author) . ' Artikel</p>';
        }
        // check author request
        if ($year) {
            foreach ($articles as $key => $article) {
                if (date_format(date_create($article['date']), 'Y') != $year) {
                    unset($articles[$key]);
                }
            }
            $content .= '<h2>' . $year . '</h2>';
            $content .= '<p>' . $this->countArticles('date', $year) . ' Artikel</p>';
        }
        // check number request
        if ($number and count($articles > $number)) {
            $articles = array_slice($articles, 0, $number);
        }

        foreach ($articles as $page => $article) {
            $date = $article['date'] . ' ' . $article['time'];
            $urlcategory = $CatPage->get_Href(
                $this->settings->get('cat'),
                $this->settings->get('page'),
                'c=' . $article['category']
            );
            $urlarticle = $CatPage->get_Href($this->settings->get('cat'), $page);
            // remove line breaks from template
            $template = str_replace('<br />', '', $this->settings->get('template_article_teaser'));;
            // fill template markers with content
            $template = str_replace(
                $this->_marker,
                array(
                    date_format(date_create($date), $this->settings->get('date_format_teaser')),
                    $date,
                    $article['author'],
                    '<a href="' . $urlcategory . '">' . $article['category'] . '</a>',
                    $article['title'],
                    $article['teaser'],
                    $this->settings->get('divider'),
                    $urlarticle,
                ),
                $template
            );
            $content .= $template;
        }

        // end plugin content
        $content .= '<!-- END ' . self::PLUGIN_TITLE . ' plugin content -->';

        return $content;
    }


    /**
     * returns a list of all existing categories
     *
     * @param string $attribute of article to list
     *
     * @return string html
     */
    private function listAttribute($attribute)
    {
        global $CatPage;

        // initialize return content, begin plugin content
        $content = '<!-- BEGIN ' . self::PLUGIN_TITLE . ' plugin content -->';
        $content .= '<ul>';

        $list = array();

        // get all values of given attribute once
        foreach ($this->getArticles() as $article) {
            if ($attribute == 'date') {
                $year = date_format(date_create($article[$attribute]) , 'Y');
                $list[$year] = TRUE;
            } else {
                $list[$article[$attribute]] = TRUE;
            }
        }
        // build list
        foreach ($list as $key => $value) {
            $url = $CatPage->get_Href(
                $this->settings->get('cat'),
                $this->settings->get('page'),
                // first letter of attribute = request key
                substr($attribute, 0, 1) . '=' . $key
            );
            $count = $this->countArticles($attribute, $key);
            $content .= '<li><a href="' . $url . '">' . $key . '</a> (' . $count . ')</li>';
        }
        $content .= '</ul>';

        // end plugin content
        $content .= '<!-- END ' . self::PLUGIN_TITLE . ' plugin content -->';

        return $content;
    }

    /**
     * returns all articles meta data
     * 
     * @return array with article information
     */
    private function getArticles()
    {
        global $CatPage;

        $blog_category = $this->settings->get('cat');

        // get blog article pages
        $pagearray = $CatPage->get_PageArray(
            $blog_category,
            array(EXT_PAGE, EXT_HIDDEN)
        );
        // remove page = category
        for ($i=0; $i < count($pagearray); $i++) {
            if ($blog_category == $pagearray[$i]) {
                unset($pagearray[$i]);
            }
        }
        $pagearray = array_values($pagearray);

        // initialize array for article meta data
        $articles = array();
        foreach ($pagearray as $page) {
            // get whole page content
            $pagecontent = $CatPage->get_PageContent($blog_category, $page);
            // get lines
            $pagedata = explode("\n", $pagecontent);
            // only add meta lines (1-6)
            $meta = array_map(trim, array_slice($pagedata, 1, count($this->_metavalues)));
            $articles[$page] = array_combine($this->_metavalues, $meta);
        }

        return $articles;
    }

    /**
     * returns number of articles in given filterkey
     *
     * @param  string $key       to identify attribute
     * @param  string $attribute to count
     *
     * @return integer           number of articles
     */
    private function countArticles($key, $attribute)
    {
        $number = 0;
        foreach ($this->getArticles() as $article) {
            if ($key == 'date') {
                $value = date_format(date_create($article[$key]), 'Y');
            } else {
                $value = $article[$key];
            }
            if ($value == $attribute) {
                $number++;
            }
        }
        return $number;
    }

    /**
     * returns warning of wrong or no mode
     *
     * @return string html output
     */
    private function noMode()
    {
        $content = 'Bitte einen gültigen Modus angeben.';
        return $content;
    }


    /**
     * sets backend configuration elements and template
     *
     * @return Array configuration
     */
    function getConfig()
    {
        $config = array();

        // read configuration values
        foreach ($this->_confdefault as $key => $value) {
            // handle each form type
            switch ($value[1]) {
            case 'text':
                $config[$key] = $this->confText(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $value[2],
                    $value[3],
                    $value[4],
                    $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_error'
                    )
                );
                break;

            case 'textarea':
                $config[$key] = $this->confTextarea(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $value[2],
                    $value[3],
                    $value[4],
                    $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_error'
                    )
                );
                break;

            case 'password':
                $config[$key] = $this->confPassword(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $value[2],
                    $value[3],
                    $value[4],
                    $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_error'
                    ),
                    $value[5]
                );
                break;

            case 'check':
                $config[$key] = $this->confCheck(
                    $this->_admin_lang->getLanguageValue('config_' . $key)
                );
                break;

            case 'radio':
                $descriptions = array();
                foreach ($value[2] as $label) {
                    $descriptions[$label] = $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_' . $label
                    );
                }
                $config[$key] = $this->confRadio(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $descriptions
                );
                break;

            case 'select':
                $descriptions = array();
                foreach ($value[2] as $label) {
                    $descriptions[$label] = $this->_admin_lang->getLanguageValue(
                        'config_' . $key . '_' . $label
                    );
                }
                $config[$key] = $this->confSelect(
                    $this->_admin_lang->getLanguageValue('config_' . $key),
                    $descriptions,
                    $value[3]
                );
                break;

            default:
                break;
            }
        }

        // read admin.css
        $admin_css = '';
        $lines = file('../plugins/' . self::PLUGIN_TITLE. '/admin.css');
        foreach ($lines as $line_num => $line) {
            $admin_css .= trim($line);
        }

        // add template CSS
        $template = '<style>' . $admin_css . '</style>';

        // build Template
        $template .= '
            <div class="devblog-admin-header">
            <span>'
                . $this->_admin_lang->getLanguageValue(
                    'admin_header',
                    self::PLUGIN_TITLE
                )
            . '</span>
            <a href="' . self::PLUGIN_DOCU . '" target="_blank">
            <img style="float:right;" src="' . self::LOGO_URL . '" />
            </a>
            </div>
        </li>
        <li class="mo-in-ul-li ui-widget-content devblog-admin-li">
            <div class="devblog-admin-subheader">'
            . $this->_admin_lang->getLanguageValue('admin_initial')
            . '</div>
            <div class="devblog-single-conf">
                {cat_text}
                {cat_description}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['cat'][0] .']
                </span>
            </div>
            <div class="devblog-single-conf">
                {page_text}
                {page_description}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['page'][0] .']
                </span>
            </div>
        </li>
        <li class="mo-in-ul-li ui-widget-content devblog-admin-li">
            <div class="devblog-admin-subheader">'
            . $this->_admin_lang->getLanguageValue('admin_display')
            . '</div>
            <div class="devblog-single-conf">
                {divider_text}
                {divider_description}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['divider'][0] .']
                </span>
            </div>
            <div class="devblog-single-conf">
                {date_format_teaser_text}
                {date_format_teaser_description}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['date_format_teaser'][0] .']
                </span>
            </div>
            <div class="devblog-single-conf">
                {date_format_article_text}
                {date_format_article_description}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['date_format_article'][0] .']
                </span>
            </div>
        </li>
        <li class="mo-in-ul-li ui-widget-content devblog-admin-li">
            <div class="devblog-admin-subheader">'
            . $this->_admin_lang->getLanguageValue('admin_templates')
            . '</div>
            <div class="devblog-single-conf">
                {template_article_teaser_description}<br />
                {template_article_teaser_textarea}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['template_article_teaser'][0] .']
                </span>
            </div>
            <div class="devblog-single-conf">
                {template_article_header_description}<br />
                {template_article_header_textarea}
                <span class="devblog-admin-default">
                    [' . $this->_confdefault['template_article_header'][0] .']
                </span>
        ';

        $config['--template~~'] = $template;

        return $config;
    }

    /**
     * sets default backend configuration elements, if no plugin.conf.php is
     * created yet
     *
     * @return Array configuration
     */
    function getDefaultSettings()
    {
        $config = array('active' => 'true');
        foreach ($this->_confdefault as $elem => $default) {
            $config[$elem] = $default[0];
        }
        return $config;
    }

    /**
     * sets backend plugin information
     *
     * @return Array information
     */
    function getInfo()
    {
        global $ADMIN_CONF;

        $this->_admin_lang = new Language(
            $this->PLUGIN_SELF_DIR
            . 'lang/admin_language_'
            . $ADMIN_CONF->get('language')
            . '.txt'
        );

        // build plugin tags
        $tags = array();
        foreach ($this->_plugin_tags as $key => $tag) {
            $tags[$tag] = $this->_admin_lang->getLanguageValue('tag_' . $key);
        }

        $info = array(
            '<b>' . self::PLUGIN_TITLE . '</b> ' . self::PLUGIN_VERSION,
            self::MOZILO_VERSION,
            $this->_admin_lang->getLanguageValue(
                'description',
                htmlspecialchars($this->_plugin_tags['tag1'], ENT_COMPAT, 'UTF-8')
            ),
            self::PLUGIN_AUTHOR,
            array(
                self::PLUGIN_DOCU,
                self::PLUGIN_TITLE . ' '
                . $this->_admin_lang->getLanguageValue('on_devmount')
            ),
            $tags
        );

        return $info;
    }

    /**
     * creates configuration for text fields
     *
     * @param string $description Label
     * @param string $maxlength   Maximum number of characters
     * @param string $size        Size
     * @param string $regex       Regular expression for allowed input
     * @param string $regex_error Wrong input error message
     *
     * @return Array  Configuration
     */
    protected function confText(
        $description,
        $maxlength = '',
        $size = '',
        $regex = '',
        $regex_error = ''
    ) {
        // required properties
        $conftext = array(
            'type' => 'text',
            'description' => $description,
        );
        // optional properties
        if ($maxlength != '') {
            $conftext['maxlength'] = $maxlength;
        }
        if ($size != '') {
            $conftext['size'] = $size;
        }
        if ($regex != '') {
            $conftext['regex'] = $regex;
        }
        if ($regex_error != '') {
            $conftext['regex_error'] = $regex_error;
        }
        return $conftext;
    }

    /**
     * creates configuration for textareas
     *
     * @param string $description Label
     * @param string $cols        Number of columns
     * @param string $rows        Number of rows
     * @param string $regex       Regular expression for allowed input
     * @param string $regex_error Wrong input error message
     *
     * @return Array  Configuration
     */
    protected function confTextarea(
        $description,
        $cols = '',
        $rows = '',
        $regex = '',
        $regex_error = ''
    ) {
        // required properties
        $conftext = array(
            'type' => 'textarea',
            'description' => $description,
        );
        // optional properties
        if ($cols != '') {
            $conftext['cols'] = $cols;
        }
        if ($rows != '') {
            $conftext['rows'] = $rows;
        }
        if ($regex != '') {
            $conftext['regex'] = $regex;
        }
        if ($regex_error != '') {
            $conftext['regex_error'] = $regex_error;
        }
        return $conftext;
    }

    /**
     * creates configuration for password fields
     *
     * @param string  $description Label
     * @param string  $maxlength   Maximum number of characters
     * @param string  $size        Size
     * @param string  $regex       Regular expression for allowed input
     * @param string  $regex_error Wrong input error message
     * @param boolean $saveasmd5   Safe password as md5 (recommended!)
     *
     * @return Array   Configuration
     */
    protected function confPassword(
        $description,
        $maxlength = '',
        $size = '',
        $regex = '',
        $regex_error = '',
        $saveasmd5 = true
    ) {
        // required properties
        $conftext = array(
            'type' => 'text',
            'description' => $description,
        );
        // optional properties
        if ($maxlength != '') {
            $conftext['maxlength'] = $maxlength;
        }
        if ($size != '') {
            $conftext['size'] = $size;
        }
        if ($regex != '') {
            $conftext['regex'] = $regex;
        }
        $conftext['saveasmd5'] = $saveasmd5;
        return $conftext;
    }

    /**
     * creates configuration for checkboxes
     *
     * @param string $description Label
     *
     * @return Array  Configuration
     */
    protected function confCheck($description)
    {
        // required properties
        return array(
            'type' => 'checkbox',
            'description' => $description,
        );
    }

    /**
     * creates configuration for radio buttons
     *
     * @param string $description  Label
     * @param string $descriptions Array Single item labels
     *
     * @return Array Configuration
     */
    protected function confRadio($description, $descriptions)
    {
        // required properties
        return array(
            'type' => 'select',
            'description' => $description,
            'descriptions' => $descriptions,
        );
    }

    /**
     * creates configuration for select fields
     *
     * @param string  $description  Label
     * @param string  $descriptions Array Single item labels
     * @param boolean $multiple     Enable multiple item selection
     *
     * @return Array   Configuration
     */
    protected function confSelect($description, $descriptions, $multiple = false)
    {
        // required properties
        return array(
            'type' => 'select',
            'description' => $description,
            'descriptions' => $descriptions,
            'multiple' => $multiple,
        );
    }

    /**
     * throws styled message
     *
     * @param string $type Type of message ('ERROR', 'SUCCESS')
     * @param string $text Content of message
     *
     * @return string HTML content
     */
    protected function throwMessage($text, $type)
    {
        return '<div class="'
                . strtolower(self::PLUGIN_TITLE . '-' . $type)
            . '">'
            . '<div>'
                . $this->_cms_lang->getLanguageValue(strtolower($type))
            . '</div>'
            . '<span>' . $text. '</span>'
            . '</div>';
    }

}

?>
