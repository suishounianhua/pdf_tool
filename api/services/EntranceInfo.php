<?php

use Phalcon\Di;

class EntranceInfo
{
    private $_entranceConfig;
    private $_empInfo;
    // PId restrict info
    public $_restrictedPIds;
    private $_PIdClueConfig;
    private $_AliasFields;

    // EmpId restrict info;
    private $_restrictedEmpIDsFromDataReportChain;
    private $_restrictedEmpIdsFromProjectGroup;
    private $_intersectionRestrictedEmpIDs;
    private $_EmpIdClueConfig;
    private $_GroupRestrict;
    private $_EIdRestrictCondition;

    public function setEntrance($controller, $action)
    {
        $interfaceConfig = new InterfaceConfig();
        $this->_entranceConfig = $interfaceConfig->getConfig($controller, $action);
        $this->_PIdClueConfig = json_decode($this->_entranceConfig['PIDClue'], true);
        $this->_EmpIdClueConfig = json_decode($this->_entranceConfig['EmpIDClue'], true);
        $this->_AliasFields = json_decode($this->_entranceConfig['AliasFields'], true);
        $this->_GroupRestrict = $this->_entranceConfig['GroupRestrict'];
    }

    public function setLogin($userInfo)
    {
        $this->_empInfo = $userInfo;
        $this->_restrictedPIds = CacheUtil::getCached(
            'restrictedPIdsfor'.$userInfo['EmpId'],
            function () use ($userInfo){
              return array_merge(array_unique(array_column(ProjectEmployeeGroup::findEnhance('ProjectEmployeeGroup', ['EmpId'=>$userInfo['EmpId']])->toArray(), 'ProjectId')));
            }
        );
        $this->_restrictedEmpIDsFromDataReportChain = Employee::getEmpForDataReport($userInfo['EmpId']);
        Di::getDefault()->get('logger')->log(
            "accessable projectIds : " . json_encode($this->_restrictedPIds, JSON_UNESCAPED_UNICODE)
        );
        Di::getDefault()->get('logger')->log(
            "PIdClueConfig projectIds : " . json_encode($this->_PIdClueConfig, JSON_UNESCAPED_UNICODE)
        );
        Di::getDefault()->get('logger')->log(
            "accessable EmpIDs : " . json_encode($this->_restrictedEmpIDsFromDataReportChain, JSON_UNESCAPED_UNICODE)
        );
        Di::getDefault()->get('logger')->log(
            "EmpIdClueConfig projectIds : " . json_encode($this->_EmpIdClueConfig, JSON_UNESCAPED_UNICODE)
        );
    }

    public function setProjectGroupRestrict($projectId, $empId)
    {
        $this->_restrictedEmpIdsFromProjectGroup = CacheUtil::getCached(
            'restrictedGroupedEmpIdsInProjectIdfor'.$empId.$projectId,
            function () use ($projectId, $empId) {
                $empIds = ProjectEmployeeGroup::getEmpGroupRestrict($empId, $projectId);
                return $empIds ? array_column($empIds->toArray(), 'EmpId') : [];
            }
        );
        $intersection = array_intersect(
            $this->_restrictedEmpIDsFromDataReportChain,
            $this->_restrictedEmpIdsFromProjectGroup
        );
        $this->_intersectionRestrictedEmpIDs = array();
        Di::getDefault()->get('logger')->log('from group restrict '
            .json_encode($this->_restrictedEmpIdsFromProjectGroup)
            . ' from data report chain restrict '
            .json_encode($this->_restrictedEmpIDsFromDataReportChain)
        );
        foreach ($intersection as $val) {
            array_push($this->_intersectionRestrictedEmpIDs, $val);
        }
    }

    public function getConfig(){
        return $this->_entranceConfig;
    }

    public function checkOptionsEmpIdRestrict($postOptions)
    {
        // 所以这里的数据限制有可能只有汇报链
        if (sizeof($this->_AliasFields) > 0){
            foreach ($this->_AliasFields as $AliasSrc => $AliasTarget) {
                $postOptions[$AliasTarget] = $postOptions[$AliasSrc];
            }
        }
//        $clueSet = json_decode($this->_entranceConfig['PIDClue'], true);
        $clueSet = $this->_EmpIdClueConfig;
        Di::getDefault()->get('logger')->log(
            "going to check: " . json_encode($postOptions, JSON_UNESCAPED_UNICODE)
        );
        Di::getDefault()->get('logger')->log(
            "with EmpId clue: " . json_encode($clueSet, JSON_UNESCAPED_UNICODE)
        );
        return $this->checkOptionsRestrict($postOptions, $clueSet, 'CrtEmpId');
    }

    public function checkOptionsPIdRestrict($postOptions)
    {
        if (sizeof($this->_AliasFields) > 0){
            foreach ($this->_AliasFields as $AliasSrc => $AliasTarget) {
                $postOptions[$AliasTarget] = $postOptions[$AliasSrc];
            }
        }

//        $clueSet = json_decode($this->_entranceConfig['PIDClue'], true);
        $clueSet = $this->_PIdClueConfig;
        Di::getDefault()->get('logger')->log(
            "going to check: " . json_encode($postOptions, JSON_UNESCAPED_UNICODE)
        );
        Di::getDefault()->get('logger')->log(
            "with pid clue: " . json_encode($clueSet, JSON_UNESCAPED_UNICODE)
        );
        return $this->checkOptionsRestrict($postOptions, $clueSet, 'ProjectId');
    }

    private function checkOptionsRestrict($postOptions, $clueSet, $targetType)
    {
        foreach($clueSet as $clue) {
            if (sizeof($clue) > 0
                // 第一条clue存在primaryKey
                && array_key_exists('primaryKey', $clue[0])
                // 提交参数中是否有这个primaryKey
                && array_key_exists($clue[0]['primaryKey'], $postOptions)
                // clue的终点是否有outerKey
                && array_key_exists('outerKey', $clue[sizeof($clue) - 1])
                // clue的终点outerKey是否为要验证的type
                && $clue[sizeof($clue) - 1]['outerKey'] == $targetType
                // 根据clue jointable查找对应资源是否存在
                && $this->checkRestrict($postOptions[$clue[0]['primaryKey']], $clue, $targetType)){
                // 以上条件均满足说明本条资源限制没发生问题
                continue;
            } else {
                Di::getDefault()->get('logger')->log(
                    "clue fail: " . sizeof($clue) . ' ' . json_encode($clue[0], JSON_UNESCAPED_UNICODE)
                    . ' ' . json_encode($clue[sizeof($clue) - 1], JSON_UNESCAPED_UNICODE)
                    . ' ' . $targetType . ' ' . array_key_exists('primaryKey', $clue[0])
                    . ' ' . array_key_exists($clue[0]['primaryKey'], $postOptions)
                    . ' ' . array_key_exists('outerKey', $clue[sizeof($clue) - 1])
                    . ' ' . ($clue[sizeof($clue) - 1]['outerKey'] == $targetType)
                );
                return false;
            }
        }
        return true;
    }

    private function checkRestrict($srcID, $clue, $targetType)
    {
        $resID = $srcID;
        Di::getDefault()->get('logger')->log(
            "source resource ID: " . json_encode($srcID, JSON_UNESCAPED_UNICODE)
        );
        foreach ($clue as $rule) {

            $resModel = $rule['table'];
            $resType = $rule['primaryKey'];
            $targetType = $rule['outerKey'];

            // 如果当前资源类型和目标资源类型相同, 那么本次查询需要获取的目标资源ID其实就是当前的资源ID
            // 这个设计是为了解决直接给出ProjectId/EmpId时的检查过程
            if ($resType == $targetType) {
                continue;
            }

            // 检查相关model是否存在
            if (!class_exists($resModel)){
                Di::getDefault()->get('logger')->log(
                    "no related class: " . json_encode($resModel, JSON_UNESCAPED_UNICODE)
                );
                return false;
            }

            $tempModel = new $rule['table'];
            $item = $tempModel::findFirst($tempModel::simpleWhere([
                'DelFlg' => 0,
                $resType => $resID
            ]));
            // 检查相关数据是否存在
            if (!$item) {
                Di::getDefault()->get('logger')->log(
                    "no related item: " . json_encode($item, JSON_UNESCAPED_UNICODE)
                );
                return false;
            }
            // 检查相关目标字段是否存在
            if (!property_exists($item, $targetType)){
                Di::getDefault()->get('logger')->log(
                    "no related property: " . json_encode($item, JSON_UNESCAPED_UNICODE)
                );
                return false;
            }
            Di::getDefault()->get('logger')->log(
                "related full resource: " . json_encode($item, JSON_UNESCAPED_UNICODE)
            );
            $resID = $item->$targetType;
        }

        Di::getDefault()->get('logger')->log(
            "related resource: " . $targetType . ' ' . $resID
        );
        // 根据指定的目标类型检查范围
        return in_array($resID, $this->getRestrictedResource($targetType));
    }

    private function getRestrictedResource($targetType) {
        if ($targetType == 'ProjectId'){
            return $this->_restrictedPIds;
        }
        if ($targetType == 'CrtEmpId') {
            if ($this->_GroupRestrict == 0) {
                return $this->_restrictedEmpIDsFromDataReportChain;
            } else {
                return $this->_intersectionRestrictedEmpIDs;
            }
        }
        return array();
    }

    /**
     * Attach restricted limit to find conditions
     *
     * @param array $findConditions
     * @param number $restrictType 
     *      type| PIdRestricted | EIdRestricted | GIdRestricted |
     *      0   |       1       |       0       |       0       |
     *      1   |       0       |       1       |       0       |
     *      2   |       1       |       1       |       1       |
     *      3   |       0       |       1       |       1       |
     * @param string $tablePrefix 0: not use table prefix, 1: use table prefix
     * @param string $pIdTableNm target table which contains ProjectId field to restrict
     * @param string $eIdTableNm target table which contains CrtEmpId field to restrict
     * @return void
     */
    public function attachRestrictCondition(&$findConditions, $restrictType, $tablePrefix = 0, $pIdTableNm = '', $eIdTableNm = '', $pIdKey = 'ProjectId', $eIdKey = 'CrtEmpId')
    {
        Di::getDefault()->get('logger')->log('findConditions b4 attached: '.json_encode($findConditions));
        if (!array_key_exists('conditions', $findConditions)){
            return;
        }
        if (!array_key_exists('bind', $findConditions)){
            return;
        }
        $restrictedItems = array();
        $queryKey = createRandomChr();
        switch ($restrictType)
        {
            case 0:
                $restrictedIds = $this->_restrictedPIds ?: [''];
                if (!$restrictedIds) return;
                array_push($restrictedItems, [
                    'primaryKey' => $pIdKey,
                    'restricted' => $restrictedIds,
                    'table' => $pIdTableNm
                ]);
                break;
            case 1:
                $restrictedIds = $this->_restrictedEmpIDsFromDataReportChain ?: [''];
//                Di::getDefault()->get('logger')->log('this is report chain ids: '.json_encode($restrictedIds));
                if (!$restrictedIds) return;
                array_push($restrictedItems, [
                    'primaryKey' => $eIdKey,
                    'restricted' => $restrictedIds,
                    'table' => $eIdTableNm
                ]);
                break;
            case 2:
                $restrictedPIds = $this->_restrictedPIds ?: [''];
                $restrictedEIds = $this->_intersectionRestrictedEmpIDs ?: [''];
                Di::getDefault()->get('logger')->log('most restricted case!!! '
                    .json_encode($restrictedPIds)
                    .json_encode($restrictedEIds)
                );
                Di::getDefault()->get('logger')->log('eId from data report!!! '
                    .json_encode($this->_restrictedEmpIDsFromDataReportChain)
                    .'eId from group'
                    .json_encode($this->_intersectionRestrictedEmpIDs)
                );
                if (!$restrictedPIds && !$restrictedEIds) return;
                array_push($restrictedItems, [
                    'primaryKey' => $pIdKey,
                    'restricted' => $restrictedPIds,
                    'table' => $pIdTableNm
                ], [
                    'primaryKey' => $eIdKey,
                    'restricted' => $restrictedEIds,
                    'table' => $eIdTableNm
                ]);
                break;
            case 3:
                $restrictedPIds = $this->_restrictedPIds ?: [''];
                $restrictedEIds = $this->_intersectionRestrictedEmpIDs ?: [''];
                Di::getDefault()->get('logger')->log('most restricted case!!! '
                    .json_encode($restrictedPIds)
                    .json_encode($restrictedEIds)
                );
                Di::getDefault()->get('logger')->log('eId from data report!!! '
                    .json_encode($this->_restrictedEmpIDsFromDataReportChain)
                    .'eId from group'
                    .json_encode($this->_intersectionRestrictedEmpIDs)
                );
                if (!$restrictedPIds && !$restrictedEIds) return;
                array_push($restrictedItems, [
                    'primaryKey' => $eIdKey,
                    'restricted' => $restrictedEIds,
                    'table' => $eIdTableNm
                ]);
                break;
            default:
                return;
        }
        foreach ($restrictedItems as $item) {
            if ($tablePrefix) {
                $findConditions['conditions'] = $findConditions['conditions'] . ' AND ' . $item['table'] . '.' . $item['primaryKey'] .' in ({' . $item['primaryKey'].$queryKey . ':array})';
            } else {
                $findConditions['conditions'] = $findConditions['conditions'] . ' AND ' . $item['primaryKey'] . ' in ({' . $item['primaryKey'].$queryKey . ':array})';
            }
//            Di::getDefault()->get('logger')->log('$item'.json_encode($item));
            $findConditions['bind'] = array_merge($findConditions['bind'], [$item['primaryKey'].$queryKey => $item['restricted']]);
        }
        Di::getDefault()->get('logger')->log('findConditions after attached: '.json_encode($findConditions));
    }

    public function getEntranceConfig()
    {
        return $this->_entranceConfig;
    }
}

function createRandomChr($length = 6)
{ 
    $randchr = ''; 
    for ($i = 0; $i < $length; $i++) { 
        $randchr .= chr(mt_rand(65, 90)); 
    } 
    return $randchr; 
}
