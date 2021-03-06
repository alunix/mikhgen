<?php
namespace app\models;

use app\components\AppHelper;
use DateTime;
use Exception;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception as Exception2;

/**
* This is the model class for table "sales".
*
* @property string $id
* @property string $idStamp
* @property string $saleDate
* @property string $name
* @property int $price
* @property string $agenCode
* @property string $profileName
* @property string $profileAlias
* @property string $duration
* @property string $ip
* @property string $mac
* @property string $comment
* @property string $sampleName
* @property string $smsSentDate
*/
class Sales extends ActiveRecord
{
    const SOURCE_DB = 1;
    const SOURCE_API = 2;
    const SOURCE_BOTH = 3;
    
    
    public static $monthCodes = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'des'
    ];
        
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sales';
    }

    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['year', 'month'], 'safe'],
            [['id'], 'required'],
            [['saleDate', 'smsSentDate'], 'safe'],
            [['price'], 'integer'],
            [['id'], 'string', 'max' => 8],
            [['idStamp'], 'string', 'max' => 20],
            [['name', 'agenCode', 'profileName', 'profileAlias', 'duration', 'ip', 'mac'], 'string', 'max' => 45],
            [['comment'], 'string', 'max' => 100],
            [['sampleName'], 'string', 'max' => 500],
            [['id'], 'unique'],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'saleDate' => 'Sale Date',
            'name' => 'Name',
            'price' => 'Price',
            'agenCode' => 'Agen Code',
            'profileName' => 'Profile Name',
            'profileAlias' => 'Profile Alias',
            'duration' => 'Duration',
            'ip' => 'Ip',
            'mac' => 'Mac',
            'comment' => 'Comment',
        ];
    }
    
    /**
     * 
     * Get sales with some parameter
     * 
     * @param type $agenCode
     * @param type $year
     * @param type $month
     * @param type $source
     * @return array sales data
     * @throws Exception2
     */
    public static function getSalesWith($agenCode = null, $filterYear = null, $filterMonth = null, $filterDay = null, $source = Sales::SOURCE_BOTH)
    {
        $missingMDR = 'vc-251-08.03.19-MDR-AUG-V2J-x1-q18'; //missing
        //$sales = [];
        
        $allAgenCodes = array_map(function($x) { return $x->agenCode; }, User::getAllAgen());
        
        //NOW ALL PARAMETERS IS OPTIONAL
        /*if (!$year) $year = intval(date('Y'));
        if (!$month) $month = intval(date('m'));
        if (!$day) $day = intval(date('d'));*/
        
        $sales = self::find()
            ->filterwhere(['=', 'YEAR(saleDate)', $filterYear])
            ->andFilterWhere(['=', 'MONTH(saleDate)', $filterMonth])
            ->andFilterWhere(['=', 'DAY(saleDate)', $filterDay])
            ->andFilterWhere(['!=', 'comment', $missingMDR])
            ->andFilterWhere(['in', 'agenCode', $allAgenCodes])
            ->andFilterWhere(['=', 'agenCode', $agenCode])
            ->all();
        
        if ($source >= self::SOURCE_API)
        {
            $api = AppHelper::getApi();
            if ($api)
            {
                //$monthCode = $month ? self::$monthCodes[intval($month)] : strtolower(date('M'));
                //$monthCode .= $year ? $year : date('Y');
                if (!$filterYear || !$filterMonth) $monthCode = null;
                else $monthCode = self::$monthCodes[intval($filterMonth)].$filterYear;

                //SALEDATE-|-SELLTIME-|-NAME-|-PRICE-|-IP-|-MAC-|-DURATION-|-VCNAME-|-COMMENT
                $queryMikhmon = $api->comm("/system/script/print", [
                    '?comment' => 'mikhmon',
                    '?owner' => $monthCode,
                ]);

                //return $query;

                foreach ($queryMikhmon as $str)
                {
                    $data = explode( '-|-', $str['name']);
                    $commentData = explode('-', $data[8]);

                    $saleDate = DateTime::createFromFormat('M/d/Y', $data[0])->format('Y-m-d').' '.$data[1];
                    
                    $sale = new self([
                        'id' => $str['.id'],
                        'saleDate' => $saleDate,
                        'name' => $data[2],
                        'price' => floatVal($data[3]),
                        'ip' => $data[4],
                        'mac' => $data[5],
                        'duration' => $data[6],
                        'profileName' => $data[7],
                        'profileAlias' => $commentData[0] == 'vc' ? ($commentData[5] ?? '') : '',
                        'agenCode' => $commentData[0] == 'vc' ? ($commentData[3] ?? '') : '',
                        'comment' => $data[8],
                        'sampleName' => $str['name'],
                    ]);

                    if ($agenCode && strpos($sale['comment'], $agenCode) === false) continue;
                    if ($agenCode == 'MDR' && strpos($sale['comment'], $missingMDR) !== false) continue;
                    $sale->idStamp = $sale->name.'-'.strtotime($saleDate);

                    if ($sale->save())
                    {
                        $api->comm("/system/script/remove", [
                            ".id" => "$sale->id",
                        ]);
                    } else {
                        Yii::trace($sale->errors, 'WKWK');
                    }

                    if ($filterYear && date('Y', strtotime($saleDate)) != $filterYear) continue;
                    if ($filterMonth && date('m', strtotime($saleDate)) != $filterMonth) continue;
                    if ($filterDay && date('d', strtotime($saleDate)) != $filterDay) continue;
                    
                    if (in_array($sale->agenCode, $allAgenCodes))
                        $sales[] = $sale;
                }
                
                //           0       1       2       3        4         5         6        7
                // name = "$agen.|.$date.|.$time.|.$user.|.$profile.|.$alias.|.$price.|.$comment"
                //            0    1        2          3               4              5
                // comment = vc.|.AGEN.|.VC_ALIAS.|.TIMESTAMP.|.xMONTH_GEN_COUNT.|.qGEN_QTY
                $queryMikhgen = $api->comm("/system/script/print", [
                    '?comment' => 'mikhgen_sales',
                    '?owner' => $monthCode
                ]);
                
                foreach ($queryMikhgen as $str)
                {
                    $data = explode( '.|.', $str['name'], 8);
                    $commentData = explode('.|.', $data[7]);
                    
                    
                    $saleDate = DateTime::createFromFormat('M/d/Y', $data[1])->format('Y-m-d').' '.$data[2];
                    $sale = new self([
                        'id' => $str['.id'],
                        'saleDate' => $saleDate,
                        'name' => $data[3],
                        'price' => floatVal($data[6]),
                        'profileName' => $data[4],
                        'profileAlias' => $commentData[0] == 'vc' ? ($commentData[2] ?? '') : '',
                        'agenCode' => $commentData[0] == 'vc' ? ($commentData[1] ?? '') : '',
                        'comment' => $data[7],
                        'sampleName' => $str['name'],
                    ]);
                    
                    if ($agenCode && $sale->agenCode != $agenCode) continue;
                    $sale->idStamp = $sale->name.'-'.strtotime($saleDate);

                    if ($sale->save())
                    {
                        $api->comm("/system/script/remove", [
                            ".id" => "$sale->id",
                        ]);
                    }

                    if ($filterYear && date('Y', strtotime($saleDate)) != $filterYear) continue;
                    if ($filterMonth && date('m', strtotime($saleDate)) != $filterMonth) continue;
                    if ($filterDay && date('d', strtotime($saleDate)) != $filterDay) continue;
                    
                    if (in_array($sale->agenCode, $allAgenCodes))
                        $sales[] = $sale;
                }
            } else
            {
                throw new Exception('Api not found, please configure your api username and password');
            }
        }
        
        return $sales;
    }
    
    public function getAgen()
    {
        return $this->hasOne(User::class, ['agenCode' => 'agenCode']);
    }
    
}
