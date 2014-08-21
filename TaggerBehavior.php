<?php
/**
 * TaggerBehavior class file
 *
 * @author Dmitry Nezhelskoy <dmitry@nezhelskoy.ru>
 * @link https://github.com/nezhelskoy/yii-tagger-behavior
 * @copyright Copyright (c) 2012 Dmitry Nezhelskoy
 * @version 0.0.1
 * @license "BSD-3-Clause"
 */

/**
 * 
 * TaggerBehavior реализует управление метками (тегами) для AR модели.
 * 
 */
class TaggerBehavior extends CActiveRecordBehavior
{

    /**
     * @var string Разделитель меток в строке, получаемой от пользователя.
     */
    public $tagDelimiter = ',';

    /**
     * @var string Имя атрибута модели, который содержит строку меток, введёную пользователем.
     */
    public $modelTagAttribute = 'tags';

    /**
     * @var string Имя таблицы меток.
     */
    public $tagTable = 'tag';

    /**
     * @var string Имя первичного ключа таблицы меток.
     */
    public $tagPk = 'id';

    /**
     * @var string Имя столбца с названиями меток в таблице меток.
     */
    public $tagName = 'name';

    /**
     * @var string Имя таблицы связи модели и меток.
     */
    public $bindingTable;

    /**
     * @var string Имя идентификатора метки в таблице связи.
     */
    public $tagFk = 'tag_id';

    /**
     * @var string Имя идентификатора модели в таблице связи.
     */
    public $modelFk;

    /**
     * @var bool Флаг разрешения создавать новые метки.
     */
    public $allowTagCreation = true;

    /**
     * Возвращает строку меток, разделённых запятой, соответствующих текщему экземпляру модели
     * 
     * @return string
     * @access public
     */
    public function getTagString()
    {
        return implode($this->tagDelimiter, $this->getTags());
    }

    /**
     * getTags возвращает массив имён меток, array('tag1', 'tag2', 'tag3')
     * 
     * @return array|null
     * @access public
     */
    public function getTags()
    {
        $command = Yii::app()->db->createCommand("
            SELECT
                `{$this->tagTable}`.`{$this->tagName}` AS name
            FROM
                `{$this->tagTable}` INNER JOIN `{$this->bindingTable}`
                  ON `{$this->tagTable}`.`{$this->tagPk}` = `{$this->bindingTable}`.`{$this->tagFk}`
            WHERE
                `{$this->bindingTable}`.`{$this->modelFk}` = :model_id
        ");
        $command->bindValue(":model_id", $this->getOwner()->getPrimaryKey(), PDO::PARAM_INT);
        return $command->queryColumn();
    }

    /**
     * getCountedTags возвращает массив, каждый элемент которого является ассоциативным массивом,
     * содержащим идентификатор метки [id], имя метки [name] и общее количество
     * сущностей модели, отмеченных данной меткой [count].
     * 
     * @return array|null
     * @access public
     */
    public function getCountedTags()
    {
        return Yii::app()->db->createCommand("
            SELECT
                `{$this->bindingTable}`.`{$this->tagFk}`        AS id,
                `{$this->tagTable}`.`{$this->tagName}`          AS name,
                COUNT(`{$this->bindingTable}`.`{$this->tagFk}`) AS count
            FROM
                `{$this->bindingTable}` INNER JOIN `{$this->tagTable}`
                  ON `{$this->bindingTable}`.`{$this->tagFk}` = `{$this->tagTable}`.`{$this->tagPk}`
            GROUP BY `{$this->bindingTable}`.`{$this->tagFk}`
            ORDER BY count DESC, name
        ")->queryAll();
    }

    /**
     * Обработчик события после сохранения записи.
     * 
     * В обработчике производятся основные операции по определению связей с метками (создание/удаление),
     * а также, при необходимости, создание новых меток.
     * 
     * @param CModelEvent $event The event parameter.
     * @access public
     */
    public function afterSave($event)
    {

        // У модели существует атрибут со строкой меток
        if (isset($this->getOwner()->{$this->modelTagAttribute})) {

            $old_tags = array();
            $new_tags = array();

            if (!$this->getOwner()->getIsNewRecord()) {
                // Получаем текущие теги
                $old_tags = $this->getTags();
            }

            if (is_array($this->getOwner()->{$this->modelTagAttribute})) {
                $checking_tags = $this->getOwner()->{$this->modelTagAttribute};
            }
            else {
                $checking_tags = explode($this->tagDelimiter, $this->getOwner()->{$this->modelTagAttribute});
            }

            // Проверка корректности имён, отсутствия дубликатов и пустых строк
            foreach ($checking_tags as $checking_tag) {
                $checking_tag = trim($checking_tag);
                if (!empty($checking_tag) && !in_array($checking_tag, $new_tags)) {
                    $new_tags[] = $checking_tag;
                }
                /*
                if (preg_match('/^[\pL_-]+(\s+[\pL_-]+){0,}$/ui', $checking_tag)) {
                    $new_tags[] = $checking_tag;
                }
                */
            }

            // Обработка введённых пользователем тегов
            foreach ($new_tags as $name) {
                $id = $this->_findTag($name);
                // Тег присутствует в базе
                if ($id) {
                    // Если тег не связан с записью
                    if (!in_array($name, $old_tags)) {
                        // Создание связи с существующей меткой
                        $this->_linkTag($id);
                    }
                }
                // Тега нет в базе
                else {
                    if ($this->allowTagCreation) {
                        if ($this->_createTag($name)) {
                            $id = $this->_findTag($name);
                            // Создание связи с новой меткой
                            $this->_linkTag($id);
                        }
                    }
                }
            }

            // Если запись редактируется
            if (!$this->getOwner()->getIsNewRecord()) {
                // Получение старых тегов, отсутствующих в массиве новых тегов
                $missing_tags = array_diff($old_tags, $new_tags);
                // Удаление связей с отсутствующими тегами
                foreach ($missing_tags as $missing_tag) {
                    $id = $this->_findTag($missing_tag);
                    if ($id) {
                        $this->_unlinkTag($id);
                    }
                }
            }

        }

        parent::afterSave($event);

    }

    /**
     * Возвращает идентификатор метки, если такая существует в таблице, иначе null.
     * 
     * @param  string   $name Имя метки
     * @return int|null Массив, содержащий идентификатор и имя метки, если метка существует.
     * @access private
     */
    private function _findTag($name)
    {
        $command = Yii::app()->db->createCommand("
            SELECT
                `{$this->tagPk}` AS id
            FROM
                `{$this->tagTable}`
            WHERE
                `{$this->tagName}` = :name
        ");
        $command->bindParam(":name", $name, PDO::PARAM_STR);
        return $command->queryScalar();
    }

    /**
     * _createTag создаёт новую запись в таблице меток.
     * 
     * @param  string   $name Имя метки
     * @return int|null Количество новых записей (ожидаемое значение - 1), иначе null
     * @access private
     */
    private function _createTag($name)
    {
        $command = Yii::app()->db->createCommand("
            INSERT INTO `{$this->tagTable}` (`{$this->tagName}`) VALUES(:name)
        ");
        $command->bindParam(":name", $name, PDO::PARAM_STR);
        return $command->execute();
    }

    /**
     * _linkTag создаёт связь текущего экземпляра модели и метки tag_id.
     * 
     * @param int $tag_id Идентификатор метки.
     * @return int|null Результат выполнения команды execute().
     * @access private
     */
    private function _linkTag($tag_id)
    {
        $command = Yii::app()->db->createCommand("
            INSERT INTO `{$this->bindingTable}` (
                `{$this->modelFk}`,
                `{$this->tagFk}`
            )
            VALUES(
                {$this->getOwner()->getPrimaryKey()},
                :tag_id
            )
        ");
        $command->bindParam(":tag_id", $tag_id, PDO::PARAM_INT);
        return $command->execute();
    }

    /**
     * _unlinkTag удаляет связь текущего экземпляра модели и метки tag_id.
     * 
     * @param int $tag_id Идентификатор метки.
     * @return int|null Результат выполнения команды execute().
     * @access private
     */
    private function _unlinkTag($tag_id)
    {
        $command = Yii::app()->db->createCommand("
            DELETE FROM
                `{$this->bindingTable}`
            WHERE
                `{$this->modelFk}` = {$this->getOwner()->getPrimaryKey()} AND
                `{$this->tagFk}`   = :tag_id
        ");
        $command->bindParam(":tag_id", $tag_id, PDO::PARAM_INT);
        return $command->execute();
    }

}
