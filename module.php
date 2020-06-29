<?php

/**
 * Example module.
 */

declare(strict_types=1);

namespace MyCustomNamespace;

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleChartTrait;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return new class extends AbstractModule implements ModuleCustomInterface, ModuleChartInterface {
    use ModuleCustomTrait;
    use ModuleChartTrait;

    /**
     * Constructor.  The constructor is called on *all* modules, even ones that are disabled.
     * This is a good place to load business logic ("services").  Type-hint the parameters and
     * they will be injected automatically.
     */
    public function __construct()
    {
        // NOTE:  If your module is dependent on any of the business logic ("services"),
        // then you would type-hint them in the constructor and let webtrees inject them
        // for you.  However, we can't use dependency injection on anonymous classes like
        // this one. For an example of this, see the example-server-configuration module.
    }

    /**
     * Bootstrap.  This function is called on *enabled* modules.
     * It is a good place to register routes and views.
     *
     * @return void
     */
    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return 'GVExport';
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return 'This module creates all-in-one charts';
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return 'Iain MacDonald';
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return '2.0.0';
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://www.github.com/IJMacD/GVExport/releases';
    }

    /**
     * Where to get support for this module.  Perhaps a github repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://www.github.com/IJMacD/GVExport';
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * A main menu item for this chart.
     *
     * @param Individual $individual
     *
     * @return Menu
     */
    public function chartMenu(Individual $individual): Menu
    {
        return new Menu(
            "All-In-One",
            $this->chartUrl($individual),
            $this->chartMenuClass(),
            $this->chartUrlAttributes()
        );
    }

    public function chartTitle(Individual $individual): string
    {
        return sprintf('All-In-One Chart for %s', $individual->fullName());
    }
    
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getChartAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'];

        $individual = Factory::individual()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual, false, true);

        $user = $request->getAttribute('user');

        Auth::checkComponentAccess($this, 'chart', $tree, $user);

        $data = $this->getIndividualsAndLinks($individual);

        return $this->viewResponse($this->name() . '::chart', [
            'title'      => $this->chartTitle($individual),
            'individual' => $individual,
            'tree'       => $tree,
            'individuals'   => $data->individuals,
            'families'      => $data->families,
            'links'         => $data->links,
        ]);
    }

    protected function getIndividualsAndLinks (Individual $individual): object 
    {
        $out = (object)[];
        $out->individuals = [];
        $out->families = [];
        $out->links = [];

        foreach ($individual->childFamilies() as $fam) {
            $this->addFamily($fam, $out);
        }

        foreach ($individual->spouseFamilies() as $fam) {
            $this->addFamily($fam, $out);
        }

        return $out;
    }

    protected function addFamily (Family $family, object $out, $recursive = true): void 
    {
        if (in_array($family, $out->families)) {
            return;
        }

        $out->families[] = $family;

        $next = [];

        foreach ($family->children() as $child) {
            if (!in_array($child, $out->individuals)) {
                $out->individuals[] = $child;

                if ($recursive) {
                    foreach ($child->spouseFamilies() as $fam) {
                        $next[] = $fam;
                    }
                }
            }

            $out->links[] = $family->xref() . ' -> ' . $child->xref();
        }

        $father = $family->husband();
        if ($father)
        {
            if (!in_array($father, $out->individuals)) {
                $out->individuals[] = $father;
                if ($recursive) {
                    foreach ($father->childFamilies() as $fam) {
                        $next[] = $fam;
                    }
                }
            }
            
            $out->links[] = $father->xref() . ' -> ' . $family->xref();
        }
        
        $mother = $family->wife();
        if ($mother)
        {
            if (!in_array($mother, $out->individuals)) {
                $out->individuals[] = $mother;
                if ($recursive) {
                    foreach ($mother->childFamilies() as $fam) {
                        $next[] = $fam;
                    }
                }
            }
            
            $out->links[] = $mother->xref() . ' -> ' . $family->xref();
        }
        

        // Add new families at bottom to try to keep children together to help renderer
        foreach ($next as $fam) {
            $this->addFamily($fam, $out, $recursive);
        }
    }
};
