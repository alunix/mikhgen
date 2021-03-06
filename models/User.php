<?php
namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property integer $roleId
 * @property string $userName
 * @property string $passwordHash
 * @property string $passwordResetToken
 * @property string $email
 * @property string $authKey
 * @property string $handphone
 * @property string $agenCode
 */
class User extends ActiveRecord implements IdentityInterface
{
    public $password;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    /*
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }*/

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['userName', 'passwordHash'], 'required'],
            [['userName', 'email', 'handphone', 'agenCode', 'password'], 'string', 'max' => 45],
            [['passwordHash', 'passwordResetToken', 'authKey'], 'string', 'max' => 200],
            [['id'], 'number'],
            [['isActive'], 'boolean'],
            [['roleId'], 'default', 'value' => 2],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['userName' => $username]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'passwordResetToken' => $token
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    public function getIsAdmin()
    {
        return $this->roleId == 1;
    }
    
    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->passwordHash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->passwordHash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->authkey = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->passwordResetToken = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->passwordResetToken = null;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->password)
        {
            $this->setPassword($this->password);
        }
        
        return parent::save($runValidation, $attributeNames);
    }
    
    public function search()
    {
        return User::find()->all();
    }
    
    public static function getDropdownList($mapTo = 'userName', $condition = [])
    {
        if (!$mapTo) $mapTo = 'userName';
        
        $models = self::find()
            ->filterWhere($condition)
            ->all();
        
        if (is_array($mapTo))
        {
            $result = [];
            
            foreach ($models as $model)
            {
                $result[$model->primaryKey] = $mapTo;
            }
            
            return $result;
        }
        
        return ArrayHelper::map($models, 'id', $mapTo);
    }
    
    public static function getAllAgen()
    {
        return self::find()
            ->where(['=', 'roleId', 2])
            ->all();
    }
}
