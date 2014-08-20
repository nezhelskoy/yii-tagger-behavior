# Yii TaggerBehavior

TaggerBehavior implements manage tags for ActiveRecord models.

### Usage

For example, we will consider tagging blog posts. The database schema will have three tables: tag, post and post_to_tag table which implements many to many relation.
In your ActiveRecord model Post add tags property and behavior:

~~~php
    public $user_tags;

    ...

    public function behaviors()
    {
        return array(
            'TaggerBehavior' => array(
                'class' => 'ext.TaggerBehavior',
                'modelTagAttribute' => 'user_tags',
                'tagTable'          => 'tag',
                'modelFk'           => 'post_id',
                'bindingTable'      => 'post_to_tag',
            ),
        );
    }

    ...

    public function attributeLabels()
    {
        return array(
            ...
            'user_tags' => 'Tags',
        );
    }
~~~

After this you can use model property $user_tags for setting and getting comma separated string tags.

## License

yii-tagger-behavior is released under the BSD License. See [LICENSE.md](https://github.com/nezhelskoy/yii-tagger-behavior/blob/master/LICENSE.md) file for
details.
