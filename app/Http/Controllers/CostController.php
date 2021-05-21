<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/**
 * Class CostController
 * @package App\Http\Controllers
 */
class CostController extends Controller
{
    /**
     * Based on request params fetch data
     *
     * @return array
     */
    public function explorer()
    {
        if(isset($_GET['project_id']) & isset($_GET['client_id']))
        {
            return $this->getData($_GET['client_id'],$_GET['projects'],null);
        }
        else if(isset($_GET['cost_type_id']))
        {
            return $this->getData(null, null, $_GET['cost_type_id']);
        }
        else if (isset($_GET['client_id']))
        {
            return $this->getData($_GET['client_id'],null,null);
        }
        else
        {
            return $this->getData(null, null,null);
        }
    }

    /**
     * Fetch data as per filters
     *
     * @param $clients
     * @param $projects
     * @param $costTypes
     * @return array
     */
    private function getData($clients,$projects,$costTypes)
    {
       $filterByClient  =   !empty($clients) ? " WHERE id in (". implode (",",$clients).")" : '';
       $filterByProject =   !empty($projects) ? " AND prj.id in (". implode (",",$projects).")" : '';
       $filterByCostType=   !empty($costTypes)? " AND ctype.id in (". implode (",",$costTypes).")" : '';

        $clients= DB::select('SELECT  * FROM clients'.$filterByClient);
        $index = 0;

        foreach($clients as $client)
        {
            $amount = 0;
            $projects = DB::select('SELECT prj.id AS id, prj.Title as name,
                                            SUM(cst.Amount) as amount FROM projects prj
                                            JOIN costs cst on prj.client_id = ? AND prj.id = cst.Project_ID'.$filterByProject.'                                            JOIN cost_types ctype on cst.Cost_Type_ID = ctype.id AND ctype.parent_id is null                                              GROUP BY prj.id, prj.title,ctype.id ORDER BY prj.id,ctype.id ASC ',[$client->id]);

            foreach ($projects as $project)
            {
                $amount+=  $project->amount;
                $project->breakdown = $this->getDataByProjectId($project->id, null,$filterByCostType);
            }
            if(empty($projects))
            {
                unset($clients[$index]);
            }
            $client->amount = $amount;
            $client->breakdown = $projects;
            $index++;
        }
        return $clients;
    }

    /**
     * Fetch data as per project Id
     *
     * @param $projectId
     * @param $parentCostTypeId
     * @param $costTypeSubQuery
     * @return array|void
     */
    private function getDataByProjectId($projectId, $parentCostTypeId, $costTypeSubQuery)
    {
        $filterByParentType = isset($parentCostTypeId) ? " ctype.parent_id = $parentCostTypeId" : " ctype.parent_id is null" ;

        $sqlQuery = 'SELECT ctype.id as id, ctype.Name as name, cst.amount as amount
                        FROM costs cst
                        LEFT JOIN cost_types ctype ON cst.cost_type_id = ctype.id
                        WHERE '.$filterByParentType.' AND cst.project_id='.$projectId.' '.$costTypeSubQuery;

        $projectBreakDownByParentCostType= DB::select($sqlQuery);
        if($projectBreakDownByParentCostType)
        {
            foreach ($projectBreakDownByParentCostType as $breakdown)
            {
                $breakdown->breakdown = $this->getDataByProjectId($projectId,$breakdown->id,"");
            }
        }else {
            return ;
        }
        return $projectBreakDownByParentCostType;
    }

}
