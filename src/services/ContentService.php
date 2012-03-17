<?php
namespace Blocks;

/**
 *
 */
class ContentService extends Component
{
	/* Sections */

	/**
	 * Get all sections for the current site
	 * @return array
	 */
	public function getSections()
	{
		$sections = Section::model()->findAllByAttributes(array(
			'site_id' => b()->sites->currentSite->id,
			'parent_id' => null
		));

		return $sections;
	}

	/**
	 * Get the sub sections of another section
	 * @param int $parentId The ID of the parent section
	 * @return array
	 */
	public function getSubSections($parentId)
	{
		return Section::model()->findAllByAttributes(array(
			'parent_id' => $parentId
		));
	}

	/**
	 * Returns a Section instance, whether it already exists based on an ID, or is new
	 * @param int $sectionId The Section ID if it exists
	 * @return Section
	 */
	public function getSection($sectionId = null)
	{
		if ($sectionId)
			$section = $this->getSectionById($sectionId);

		if (empty($section))
			$section = new Section;

		return $section;
	}

	/**
	 * Get a specific section by ID
	 * @param int $sectionId The ID of the section to get
	 * @return Section
	 */
	public function getSectionById($sectionId)
	{
		return Section::model()->findById($sectionId);
	}

	/**
	 * @param $siteId
	 * @param $handle
	 * @return mixed
	 */
	public function getSectionBySiteIdHandle($siteId, $handle)
	{
		$section = Section::model()->findByAttributes(array(
			'handle' => $handle,
			'site_id' => $siteId,
		));

		return $section;
	}

	/**
	 * @param $siteId
	 * @param $handles
	 * @return mixed
	 */
	public function getSectionsBySiteIdHandles($siteId, $handles)
	{
		$sections = Section::model()->findAllByAttributes(array(
			'handle' => $handles,
			'site_id' => $siteId,
		));

		return $sections;
	}

	/**
	 * Saves a section
	 *
	 * @param            $sectionSettings
	 * @param array|null $sectionBlocks
	 * @param null       $sectionId
	 * @return \Blocks\Section
	 */
	public function saveSection($sectionSettings, $sectionBlocks = null, $sectionId = null)
	{
		$section = $this->getSection($sectionId);
		$isNewSection = $section->isNewRecord;

		$section->name = $sectionSettings['name'];
		$section->handle = $sectionSettings['handle'];
		$section->max_entries = $sectionSettings['max_entries'];
		$section->sortable = $sectionSettings['sortable'];
		$section->has_urls = $sectionSettings['has_urls'];
		$section->url_format = $sectionSettings['url_format'];
		$section->template = $sectionSettings['template'];
		$section->site_id = b()->sites->currentSite->id;

		if ($section->validate())
		{
			// Start a transaction
			$transaction = b()->db->beginTransaction();
			try
			{
				// Save the block
				$section->save();

				// Delete the previous content block selections
				if (!$isNewSection)
				{
					b()->db->createCommand()
						->where('section_id = :id', array(':id' => $section->id))
						->delete('sectionblocks');
				}

				// Add new content block selections
				if (!empty($sectionBlocks['selections']))
				{
					$sectionBlocksData = array();

					foreach ($sectionBlocks['selections'] as $sortOrder => $blockId)
					{
						$required = (isset($sectionBlocks['required'][$blockId]) && $sectionBlocks['required'][$blockId] === 'y');
						$sectionBlocksData[] = array($section->id, $blockId, $required, $sortOrder+1);
					}

					b()->db->createCommand()->insertAll('sectionblocks', array('section_id','block_id','required','sort_order'), $sectionBlocksData);
				}

				$transaction->commit();
			}
			catch (Exception $e)
			{
				$transaction->rollBack();
				throw $e;
			}
		}

		return $section;
	}

	/* Entries */

	/**
	 * Creates a new entry
	 * @param $sectionId
	 * @param $authorId
	 * @param null $parentId
	 * @return Entry
	 */
	public function createEntry($sectionId, $authorId, $parentId = null)
	{
		$entry = new Entry;
		$entry->section_id = $sectionId;
		$entry->author_id = $authorId;
		$entry->parent_id = $parentId;
		$entry->save();
		return $entry;
	}

	/**
	 * Returns an Entry instance, whether it already exists based on an ID, or is new
	 * @param int $entryId The Entry ID if it exists
	 * @return Section
	 */
	public function getEntry($entryId = null)
	{
		if ($entryId)
			$entry = $this->getEntryById($entryId);

		if (empty($entry))
			$entry = new Entry;

		return $entry;
	}

	/**
	 * @param $entryId
	 * @return mixed
	 */
	public function getEntryById($entryId)
	{
		$entry = Entry::model()->findById($entryId);
		return $entry;
	}

	/**
	 * @param $sectionId
	 * @return mixed
	 */
	public function getEntriesBySectionId($sectionId)
	{
		$entries = Entry::model()->findAllByAttributes(array(
			'section_id' => $sectionId,
		));
		return $entries;
	}

	/**
	 * @param $siteId
	 * @return array
	 */
	public function getAllEntriesBySiteId($siteId)
	{
		$entries = b()->db->createCommand()
			->select('e.*')
			->from('sections s')
			->join('entries e', 's.id = e.section_id')
			->where('s.site_id=:siteId', array(':siteId' => $siteId))
			->queryAll();
		return $entries;
	}

	/**
	 * @param $entryId
	 * @return mixed
	 */
	public function doesEntryHaveSubEntries($entryId)
	{
		$exists = Entry::model()->exists(
			'parent_id=:parentId',
			array(':parentId' => $entryId)
		);
		return $exists;
	}

	/**
	 * @param $entryId
	 * @return mixed
	 */
	public function getEntryVersionsByEntryId($entryId)
	{
		$versions = EntryVersions::model()->findAllByAttributes(array(
			'entry_id' => $entryId,
		));
		return $versions;
	}

	/**
	 * @param $versionId
	 * @return mixed
	 */
	public function getEntryVersionById($versionId)
	{
		$version = EntryVersions::model()->findByAttributes(array(
			'id' => $versionId,
		));
		return $version;
	}

	/**
	 * Creates a new draft
	 * @param int $entryId
	 * @param string $name
	 * @return Draft
	 */
	public function createDraft($entryId, $name = 'Untitled')
	{
		$draft = new Draft;
		$draft->entry_id = $entryId;
		$draft->author_id = b()->users->current->id;
		$draft->language = b()->sites->currentSite->language;
		$draft->name = $name;
		$draft->save();
		return $draft;
	}
	
	/**
	 * @param $entryId
	 * @return mixed
	 */
	public function getDraftById($draftId)
	{
		$draft = Draft::model()->findById($draftId);
		return $draft;
	}

	/**
	 * Returns the latest draft for an entry, if one exists
	 * @param int $entryId
	 * @return mixed The latest draft or null
	 */
	public function getLatestDraft($entryId)
	{
		$draft = Draft::model()->find(array(
			'condition' => 'entry_id = :entryId',
			'params' => array(':entryId' => $entryId),
			'order' => 'date_created DESC'
		));
		return $draft;
	}
}
