<?php
namespace barrelstrength\sproutforms\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use yii\base\ErrorHandler;
use craft\db\Query;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;
use craft\elements\actions\Delete;

use barrelstrength\sproutforms\elements\db\EntryQuery;
use barrelstrength\sproutforms\records\Form as FormRecord;
use barrelstrength\sproutforms\records\Entry as EntryRecord;
use barrelstrength\sproutforms\SproutForms;

/**
 * Entry represents a entry element.
 */
class Entry extends Element
{
	// Properties
	// =========================================================================
	private $form;

	public $id;
	public $formId;
	public $statusId;
	public $formName;
	public $ipAddress;
	public $userAgent;

	/**
	 * Returns the field context this element's content uses.
	 *
	 * @access protected
	 * @return string
	 */
	public function getFieldContext(): string
	{
		return 'sproutForms:' . $this->formId;
	}

	/**
	 * Returns the name of the table this element's content is stored in.
	 *
	 * @return string
	 */
	public function getContentTable(): string
	{
		return SproutForms::$api->forms->getContentTableName($this->getForm());
	}

	/**
	 * Returns the element type name.
	 *
	 * @return string
	 */
	public static function displayName(): string
	{
		return SproutForms::t('Sprout Forms Entries');
	}

	/**
	 * @inheritdoc
	 */
	public static function refHandle()
	{
		return 'entries';
	}

	/**
	 * @inheritdoc
	 */
	public static function hasContent(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasTitles(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public static function isLocalized(): bool
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasStatuses(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getCpEditUrl()
	{
		return UrlHelper::cpUrl(
			'sprout-forms/entries/edit/'.$this->id
		);
	}

	/**
	 * Use the name as the string representation.
	 *
	 * @return string
	 */
	/** @noinspection PhpInconsistentReturnPointsInspection */
	public function __toString()
	{
		try {
			return $this->getForm()->name;
		} catch (\Exception $e) {
			ErrorHandler::convertExceptionToError($e);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getFieldLayout()
	{
		return $this->getForm()->getFieldLayout();
	}

	/**
	 * @inheritDoc BaseElementModel::getStatus()
	 *
	 * @return string|null
	 */
	public function getStatus()
	{
		$statusId = $this->statusId;

		$status = SproutForms::$api->entries->getEntryStatusById($statusId);

		return $status->color;
	}

	/**
	 * Returns a list of statuses for this element type
	 *
	 * @return array
	 */
	public function getStatuses()
	{
		$statuses    = SproutForms::$api->entries->getAllEntryStatuses();
		$statusArray = [];

		foreach ($statuses as $status)
		{
			$key = $status['handle'] . ' ' . $status['color'];
			$statusArray[$key] = $status['name'];
		}

		return $statusArray;
	}

	/**
	 * Returns an array of key/value pairs to send along in payload forwarding requests
	 *
	 * @return array
	 */
	public function getPayloadFields()
	{
		$fields = array();
		$ignore = array(
			'id',
			'slug',
			'title',
			'handle',
			'locale',
			'element',
			'elementId',
		);

		$content = $this->getContent()->getAttributes();

		foreach ($content as $field => $value)
		{
			if (!in_array($field, $ignore))
			{
				$fields[$field] = $value;
			}
		}

		return $fields;
	}

	/**
	 * @inheritdoc
	 *
	 * @return FormQuery The newly created [[FormQuery]] instance.
	 */
	public static function find(): ElementQueryInterface
	{
		return new EntryQuery(get_called_class());
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSources(string $context = null): array
	{
		$sources = [
			[
			'key'   => '*',
			'label' => SproutForms::t('All Entries'),
			]
		];

		$sources[] = [
			'heading' => SproutForms::t("Forms")
		];

		// Prepare the data for our sources sidebar
		$groups = SproutForms::$api->groups->getAllFormGroups('id');
		$forms  = SproutForms::$api->forms->getAllForms();

		$noSources   = [];
		$prepSources = [];

		foreach ($forms as $form)
		{
			if ($form->groupId)
			{
				if (!isset($prepSources[$form->groupId]['heading']) && isset($groups[$form->groupId]))
				{
					$prepSources[$form->groupId]['heading'] = $groups[$form->groupId]->name;
				}

				$prepSources[$form->groupId]['forms'][$form->id] = [
					'label'    => $form->name,
					'data'     => ['formId' => $form->id],
					'criteria' => ['formId' => $form->id]
				];
			}
			else
			{
				$noSources[$form->id] = [
					'label'    => $form->name,
					'data'     => ['formId' => $form->id],
					'criteria' => ['formId' => $form->id]
				];
			}
		}

		// Build our sources for forms with no group
		foreach ($noSources as $form)
		{
			$key           = "form:" . $form['data']['formId'];
			$sources[] = [
				'key'      => $key,
				'label'    => $form['label'],
				'data'     => [
					'formId' => $form['data']['formId'],
				],
				'criteria' => [
					'formId' => $form['criteria']['formId'],
				]
			];
		}

		// Build our sources sidebar for forms in groups
		foreach ($prepSources as $source)
		{
			if (isset($source['heading']))
			{
				$sources[] = [
					'heading' => $source['heading']
				];
			}

			foreach ($source['forms'] as $form)
			{
				$key           = "form:" . $form['data']['formId'];
				$sources[] = [
					'key'      => $key,
					'label'    => $form['label'],
					'data'     => [
						'formId' => $form['data']['formId'],
					],
					'criteria' => [
						'formId' => $form['criteria']['formId'],
					]
				];
			}
		}

		return $sources;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineActions(string $source = null): array
	{
		$actions = [];

		// Delete
		$actions[] = Craft::$app->getElements()->createAction([
			'type' => Delete::class,
			'confirmationMessage' => SproutForms::t('Are you sure you want to delete the selected entries?'),
			'successMessage' => SproutForms::t('Entries deleted.'),
		]);

		return $actions;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSearchableAttributes(): array
	{
		return ['id', 'title', 'formName'];
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineSortOptions(): array
	{
		$attributes = [
			'sproutforms_entries.dateCreated' => SproutForms::t('Date Created'),
			'name'                            => SproutForms::t('Form Name'),
			'sproutforms_entries.dateUpdated' => SproutForms::t('Date Updated'),
		];

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	protected static function defineTableAttributes(): array
	{
		$attributes['title']       = ['label' => SproutForms::t('Title')];
		$attributes['formName']    = ['label' => SproutForms::t('Form Name')];
		$attributes['dateCreated'] = ['label' => SproutForms::t('Date Created')];
		$attributes['dateUpdated'] = ['label' => SproutForms::t('Date Updated')];

		return $attributes;
	}

	protected static function defineDefaultTableAttributes(string $source): array
	{
		$attributes = ['title', 'formName', 'dateCreated', 'dateUpdated'];

		return $attributes;
	}

	/**
	 * @inheritdoc
	 */
	protected function tableAttributeHtml(string $attribute): string
	{
		return parent::tableAttributeHtml($attribute);
	}

	/**
	 * @inheritdoc
	 * @throws Exception if reasons
	 */
	public function afterSave(bool $isNew)
	{
		// Get the form record
		if (!$isNew)
		{
			$record = EntryRecord::findOne($this->id);

			if (!$record)
			{
				throw new Exception('Invalid Entry ID: '.$this->id);
			}
		} else
		{
			$record = new EntryRecord();
			$record->id = $this->id;
		}

		$record->ipAddress = $this->ipAddress;
		$record->formId    = $this->formId;
		$record->statusId  = $this->statusId;
		$record->userAgent = $this->userAgent;

		$record->save(false);

		parent::afterSave($isNew);
	}

	/**
	 * Returns the fields associated with this form.
	 *
	 * @return array
	 */
	public function getFields()
	{
		return $this->getForm()->getFields();
	}

	/**
	 * Returns the form element associated with this entry
	 *
	 * @return FormElement
	 */
	public function getForm()
	{
		if (!isset($this->form))
		{
			$this->form = SproutForms::$api->forms->getFormById($this->formId);
		}

		return $this->form;
	}

	/**
	 * Returns the content title for this entry
	 *
	 * @return mixed|string
	 */
	public function getTitle()
	{
		return $this->getContent()->title;
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['formId'], 'required']];
	}
}