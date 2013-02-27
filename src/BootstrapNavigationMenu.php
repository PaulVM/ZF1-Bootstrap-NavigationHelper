<?php

/**
 * Extends ZF1 Navigation Menu to implement Twitter Bootstrap css classes and structure.
 * @author Paul VM (https://github.com/PaulVM) - Implemented Bootstrap class and markup injection.
 */
class Application_Helper_View_BootstrapNavigationMenu extends Zend_View_Helper_Navigation_Menu
{
    /**
     * Flag to control whether clicking top level parents simply toggles a dropdown or activates a true hyperlink.
     * Used to enable creation of a primary nav with no drop-downs.
     *
     * @var bool
     */
    protected $_parentsAreLinks = false;


    /**
     * Sets top level parents to be rendered as links. (i.e. Clicking take user to different page)
     *
     * @param void
     *
     * @return Application_Helper_View_NavigationMenu
     */
    public function setParentsAreLinks() {
        $this->_parentsAreLinks = true;
        return $this;
    }


    /**
     * Sets top level parents to be rendered as menu toggles. (i.e. Clicking opens a submenu)
     *
     * @param void
     *
     * @return Application_Helper_View_NavigationMenu
     */
    public function setParentsAreToggles() {
        $this->_parentsAreLinks = false;
        return $this;
    }


    /**
     * Returns whether parent elements should act as hyperlinks when clicked.
     *
     * @param void
     *
     * @return bool
     */
    public function getParentsAreLinks() {
        return $this->_parentsAreLinks;
    }


    /**
     * Returns whether parent elements should toggle visibility of submenus when clicked.
     *
     * @param void
     *
     * @return bool
     */
    public function getParentsAreToggles() {
        return !$this->_parentsAreLinks;
    }


    /**
     * Direct access method from view
     *
     * @param void
     *
     * @return Application_Helper_View_NavigationMenu
     */
    public function NavigationMenu()
    {
        return $this;
    }


    /**
     * Returns an HTML string containing an 'a' element for the given page if
     * the page's href is not empty, and a 'span' element if it is empty
     *
     * Overrides {@link Zend_View_Helper_Navigation_Abstract::htmlify()}.
     *
     * @param  Zend_Navigation_Page $page  page to generate HTML for
     * @return string                      HTML string for the given page
     */
    public function htmlify(Zend_Navigation_Page $page)
    {
        // get label and title for translating
        $label = $page->getLabel();
        $title = $page->getTitle();

        $class = $page->getClass();
        $class = str_replace('dropdown-menu', '', $class);

        // translate label and title?
        if ($this->getUseTranslator() && $t = $this->getTranslator()) {
            if (is_string($label) && !empty($label)) {
                $label = $t->translate($label);
            }
            if (is_string($title) && !empty($title)) {
                $title = $t->translate($title);
            }
        }

        // get attribs for element
        $attribs = array(
            'id'     => $page->getId(),
            'title'  => $title,
            'class'  => (('dropdown' === $class) ? 'dropdown-toggle' : $class)
        );

        // does page have a href?
        if ($href = $page->getHref()) {
            $element = 'a';
            $attribs['href'] = $href;
            $attribs['target'] = $page->getTarget();
            $attribs['accesskey'] = $page->getAccessKey();

            if ($this->getParentsAreToggles() and 'dropdown' === $class) {
                $attribs['href'] = '#';
                $attribs['data-toggle'] = "dropdown";
            }
        } else {
            $element = 'span';
        }

        $append = '';

        if ($this->getParentsAreToggles()
            and 'dropdown' === $class
            and $page->get('depth') === 0)
        {
            $append = ' <b class="caret"></b>';
        }

        $pageHTML = '<' . $element . $this->_htmlAttribs($attribs) . '>'
            . $this->view->escape($label)
            . $append
            . '</' . $element . '>';
        return $pageHTML;
    }


    /**
     * Generates menu markup
     *
     * @param Zend_Navigation_Container $container Parent container of menu branch to be rendered. (Doesn't have to be the top level!)
     * @param string $ulClass A CSS class to add to the top level <ul> tag.
     * @param string $indent A string to use for indentation - to help generate pretty markup.
     * @param int $minDepth A number showing how deep in the navigation tree to start rendering.
     * @param int $maxDepth The maximum depth to render. Elements below this depth will be ignored, even if they are active and visible.
     * @param bool $onlyActive Whether to exclude items if they are not the currently active page or its direct ancestors.
     *
     * @return string HTML markup for rendering in a view.
     */

    protected function _renderMenu(Zend_Navigation_Container $container,
                                   $ulClass,
                                   $indent,
                                   $minDepth,
                                   $maxDepth,
                                   $onlyActive)
    {
        $html = '';
        if (empty($indent)) {
            $indent = "\t";
        }

        // find deepest active
        $foundPage = null;
        $foundDepth = null;

        if ($found = $this->findActive($container, $minDepth, $maxDepth)) {
            $foundPage = $found['page'];
            $foundDepth = $found['depth'];
        }

        // create iterator
        $iterator = new RecursiveIteratorIterator($container, RecursiveIteratorIterator::SELF_FIRST);
        if (is_int($maxDepth)) {
            $iterator->setMaxDepth($maxDepth);
        }

        // iterate container
        $prevDepth = -1;
        foreach ($iterator as $page) {
            $depth = $iterator->getDepth();

            $page->set('depth', $depth);

            $isActive = $page->isActive(true);
            $moduleName = $page->get('module');

            if ($depth < $minDepth || !$this->accept($page)) {
                // page is below minDepth or not accepted by acl/visibilty
                continue;
            } else if ($onlyActive && !$isActive) {
                // page is not active itself, but might be in the active branch
                $accept = false;
                if ($foundPage) {
                    if ($foundPage->hasPage($page)) {
                        // accept if page is a direct child of the active page
                        $accept = true;
                    } else if ($foundPage->getParent()->hasPage($page)) {
                        // page is a sibling of the active page...
                        if (!$foundPage->hasPages() ||
                            is_int($maxDepth) && $foundDepth + 1 > $maxDepth) {
                            // accept if active page has no children, or the
                            // children are too deep to be rendered
                            $accept = true;
                        }
                    }
                }

                if (!$accept) {
                    continue;
                }
            }

            // make sure indentation is correct
            $depth -= $minDepth;
            $myIndent = str_repeat($indent, $depth * 2);

            if (0 === $depth) {
                $containerClass = 'nav';
                if ($page->count()) {
                    $page->set('class', 'dropdown');
                } else {
                    $page->set('class', '');
                }
            } else {
                $containerClass = 'dropdown-menu';
                if ($page->count()) {
                    $page->set('class', 'dropdown');
                }
            }

            if ($depth > $prevDepth) {
                // We have descended one level
                if (!empty($containerClass)) {
                    $containerClass = ' class="' . trim($containerClass) . '"';
                }

                $html .=  $myIndent . "<ul$containerClass>" . self::EOL;
            } else if ($prevDepth > $depth) {
                // We have ascended to the top of the next tree - perhaps multiple levels.
                // close li/ul tags until we're at current depth
                for ($i = $prevDepth; $i > $depth; $i--) {
                    $prevIndent = str_repeat($indent, $i * 2);
                    $html .= $prevIndent . $indent .  "</li>" . self::EOL;
                    $html .= $prevIndent . "</ul>" . self::EOL;
                }
                $html .= str_repeat($indent, $depth + 1 ) . "</li>" . self::EOL;
            } else {
                $html .= str_repeat($indent, $depth * 2 + 1) . "</li>" . self::EOL;
            }

            // render li tag and page
            $liClass = ($page->get('class')) ? $page->class : '';

            // If the page had children then we must put a different class on it at different depths to enable the submenu.
            if ($page->count()) {
                $liClass = ($depth === 0 ? 'dropdown' : 'dropdown-submenu');
            }

            $liClass .= ($isActive) ? ' active' : '';

            $liClass = (!empty($liClass))? ' class="' . trim($liClass) . '"' : '';

            $html .= $myIndent . $indent . "<li$liClass>" . self::EOL
                . $myIndent . $indent . $indent . $this->htmlify($page) . self::EOL;

            // store as previous depth for next iteration
            $prevDepth = $depth;
        }

        if ($html) {
            // done iterating container; close open ul/li tags
            for ($i = $prevDepth; $i >= 0; $i--) {
                $myIndent = str_repeat($indent, $i*2);
                $html .= $myIndent . $indent . '</li>' . self::EOL;
                $html .= $myIndent . '</ul>' . self::EOL;
            }
            $html = rtrim($html, self::EOL);
        }
        return $html;
    }

    /**
     * Searches the immediate children of a container to find the first active child.
     * Useful for contructing a menu of siblings e.g. a primary nav of the top level items without any dropdowns.
     *
     * @param  Zend_Navigation_Container $container   Container to search
     * @return Zend_Navigation_Container | false
     */
    public function getFirstActiveChild(Zend_Navigation_Container $container) {
        $result = false;
        $pages = $container->getPages();
        foreach($pages as $page) {
            if ($page->isActive(true)) {
                $result = $page;
                break;
            }
        }

        return $result;
    }
}
