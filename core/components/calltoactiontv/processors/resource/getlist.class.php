<?php

/**
 * Gets a list of resources.
 *
 * @param integer $start (optional) The record to start at. Defaults to 0.
 * @param integer $limit (optional) The number of records to limit to. Defaults
 * to 10.
 * @param string $sort (optional) The column to sort by. Defaults to name.
 * @param string $dir (optional) The direction of the sort. Defaults to ASC.
 * @return array An array of modResources
 *
 * @package modx
 * @subpackage processors.resource
 */
class callToActionTVResourceGetListProcessor extends modObjectGetListProcessor
{
    public $classKey = 'modResource';
    public $languageTopics = array('resource');
    public $defaultSortField = 'pagetitle';
    public $permission = 'view';

    protected $charset = '';
    protected $tvObject;
    protected $inputProperties = [];

    /**
     * calltoactiontvResourceGetListProcessor constructor.
     *
     * @param modX $modx
     * @param array $properties
     */
    public function __construct(modX &$modx, array $properties = array())
    {
        parent::__construct($modx, $properties);

        $this->charset = $this->modx->getOption('modx_charset',null, 'UTF-8');
    }

    /**
     * @param $pagetitle
     * @return string
     */
    protected function preparePagetitle($pagetitle)
    {
        return htmlentities($pagetitle, ENT_COMPAT, $this->charset);
    }

    /**
     * @return mixed|string
     */
    public function process()
    {
        if ($this->getProperty('tvId')) {
            $this->tvObject        = $this->modx->getObject('modTemplateVar', $this->getProperty('tvId'));
            $this->inputProperties = $this->tvObject ? $this->tvObject->get('input_properties') : [];

            if ($this->tvObject && !empty(trim($this->tvObject->get('elements')))) {
                $options = [];

                foreach (explode('||', $this->tvObject->processBindings($this->tvObject->get('elements'))) as $item) {
                    list($pagetitle, $id) = explode('==', $item);

                    $options[] = [
                        'id'        => $id,
                        'pagetitle' => $this->preparePagetitle($pagetitle)
                    ];
                }

                /* Filter results if query is set. */
                if ($this->getProperty('query')) {
                    foreach ($options as $key => $option) {
                        if (!preg_match("/{$this->getProperty('query')}/i", $option['pagetitle'])) {
                            unset($options[$key]);
                        }
                    }
                }

                $offset = $this->getProperty('start', 0);
                $limit  = $this->getProperty('limit', 20);
                $total  = count($options);

                $options = array_slice($options, $offset, $limit);

                return $this->outputArray($options, $total);
            }
        }

        return parent::process();
    }

    /**
     * @param \xPDO\Om\xPDOQuery $query
     * @return \xPDO\Om\xPDOQuery
     */
    public function prepareQueryBeforeCount(\xPDO\Om\xPDOQuery $query)
    {
        if ($this->tvObject) {
            /* Check if only resources related to the current resource context should be retrieved. */
            if (isset($this->inputProperties['limit_related_ctx']) && $this->inputProperties['limit_related_ctx'] === 'true') {
                $curResource = $this->modx->getObject('modResource', $this->getProperty('resourceId'));

                if ($curResource) {
                    $query->where(['context_key' => $curResource->get('context_key')]);
                }
            }
        }

        if ($this->getProperty('query')) {
            $query->where([
                'pagetitle:LIKE' => '%' . $this->getProperty('query') . '%'
            ]);
        }

        if (isset($this->inputProperties['where_conditions']) && !empty($this->inputProperties['where_conditions'])) {
            $whereConditions = json_decode($this->inputProperties['where_conditions'], true);

            $query->where($whereConditions);
        }

        return parent::prepareQueryBeforeCount($query);
    }

    /**
     * @return array
     */
    public function getData()
    {
        $data = parent::getData();

        /* Make sure that selected resource is included in result. For instance if the resource is on second page it would only display the resource id. */
        $selectedResource = $this->modx->getObject('modResource', $this->getProperty('selectedResourceId'));
        if ($selectedResource) {
            $data['results'][] = $selectedResource;
        }


        return $data;
    }

    /**
     * @param xPDOObject $object
     * @return array
     */
    public function prepareRow(xPDOObject $object)
    {
        $objectArray              = $object->toArray();
        $objectArray['pagetitle'] = $this->preparePagetitle($objectArray['pagetitle']);

        /* Add resource id if it is configured in TV properties. */
        if (isset($this->inputProperties) && $this->inputProperties['display_resource_id'] === 'true') {
            $objectArray['pagetitle'] .= ' (' . $objectArray['id'] . ')';
        }

        return $objectArray;
    }
}

return 'callToActionTVResourceGetListProcessor';
