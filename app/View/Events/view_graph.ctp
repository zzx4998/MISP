<?php
    if ($scope == 'event') {
        $mayModify = (($isAclModify && $event['Event']['user_id'] == $me['id'] && $event['Orgc']['id'] == $me['org_id']) || ($isAclModifyOrg && $event['Orgc']['id'] == $me['org_id']));
        $mayPublish = ($isAclPublish && $event['Orgc']['id'] == $me['org_id']);
    }
    echo $this->Html->css('font-awesome');
    echo $this->Html->css('correlation-graph');
    echo $this->Html->script('d3');
    echo $this->Html->script('correlation-graph');
    echo $this->Html->script('font-awesome-helper');
    $queryHeaders = 'Content-type: application/json' . PHP_EOL .
        'Accept: application/json' . PHP_EOL;
    $queryBody = json_encode(array(
        'returnFormat' => 'correlation-graph',
        'publish_timestamp' => '1h'
    ), JSON_PRETTY_PRINT);
    if (!$ajax):
?>
    <div class="graph-view">
<?php endif; ?>
    <span id="fullscreen-btn-correlation" class="fullscreen-btn-correlation btn btn-xs btn-primary" data-toggle="tooltip" data-placement="top" data-title="<?php echo __('Toggle fullscreen');?>"><span class="fa fa-desktop"></span></span>
    <div id="chart" style="width:100%;height:100%"></div>
        <div id="hover-menu-container" class="menu-container">
            <span class="bold hidden" id="hover-header"><?php echo __('Hover target');?></span><br />
            <ul id="hover-menu" class="menu">
            </ul>
        </div>
        <div id="selected-menu-container" class="menu-container">
            <span class="bold hidden" id="selected-header"><?php echo __('Selected');?></span><br />
            <ul id = "selected-menu" class="menu">
            </ul>
        </div>
        <ul id="context-menu" class="menu">
            <li id="expand"><?php echo __('Expand');?></li>
            <li id="context-delete"><?php echo __('Delete');?></li>
        </ul>
        <div class="control-button-group">
            <div style="float:right;">
                <button class="btn btn-inverse" onClick="toggleQueryInterface();" title="<?php echo __('Toggle the query interface on/off.');?> ">Toggle query interface</button>
                <button class="btn btn-inverse" onClick="togglePhysics();" title="<?php echo __('Toggle the physics engine on/off.');?> ">Toggle physics</button>
            </div><br />
            <div id="query_box" class="query_box">
                <div>
                    <span class="bold">URL:</span><br />
                    <input id="query_url" type="text" class="input input-xxlarge" value="/events/restSearch" /><br />
                    <span class="bold">Headers:</span><br />
                    <textarea id="query_headers" type="text" class="input input-xxlarge" rows="5" cols="30"><?php echo $queryHeaders; ?></textarea><br />
                    <span class="bold">Query body:</span><br />
                    <textarea id="query_body" type="text" class="input input-xxlarge" rows="5" cols="30"><?php echo h($queryBody); ?></textarea>
                </div>
                <button style="float:right;" class="btn btn-primary" id="submit_correlation_query">Submit</button>
            </div>
        </div>
<?php
    if (!$ajax):
?>
    </div>
<?php endif; ?>
<div id="graph_init" class="hidden" data-id="<?php echo h($id);?>" data-scope="<?php echo h($scope);?>" data-ajax="<?php echo $ajax ? 'true' : 'false'; ?>">
</div>
<?php
    $scope_list = array(
        'event' => 'event',
        'galaxy' => 'galaxies',
        'tag' => 'tags'
    );
    $params = array(
        'menuList' => $scope_list[$scope],
        'menuItem' => 'viewGraph'
    );
    if ($scope == 'event') {
        $params['mayModify'] = $mayModify;
        $params['mayPublish'] = $mayPublish;
    }
    if ($scope == 'tag') {
        if (!empty($taxoomy)) {
            $params['taxonomy'] = $taxonomy['Taxonomy']['id'];
        }
    }

    if (!$ajax) {
        echo $this->element('/genericElements/SideMenu/side_menu', $params);
    }
?>
