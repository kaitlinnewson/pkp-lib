<?php

/**
 * @file classes/announcement/AnnouncementTypeDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeDAO
 *
 * @ingroup announcement
 *
 * @see AnnouncementType
 *
 * @brief Operations for retrieving and modifying AnnouncementType objects.
 */

namespace PKP\announcement;

use APP\facades\Repo;

class AnnouncementTypeDAO extends \PKP\db\DAO
{
    /**
     * Generate a new data object.
     *
     * @return AnnouncementType
     */
    public function newDataObject()
    {
        return new AnnouncementType();
    }

    /**
     * Retrieve an announcement type by announcement type ID.
     *
     * @param ?int $typeId Announcement type ID
     * @param ?int $contextId Optional context ID
     *
     * @return AnnouncementType
     */
    public function getById($typeId, $contextId = null)
    {
        $params = [(int) $typeId];
        if ($contextId !== null) {
            $params[] = (int) $contextId;
        }
        $result = $this->retrieve(
            'SELECT * FROM announcement_types WHERE type_id = ?' .
            ($contextId !== null ? ' AND context_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Get the locale field names.
     */
    public function getLocaleFieldNames(): array
    {
        return ['name'];
    }

    /**
     * Internal function to return an AnnouncementType object from a row.
     *
     * @param array $row
     *
     * @return AnnouncementType
     */
    public function _fromRow($row)
    {
        $announcementType = $this->newDataObject();
        $announcementType->setId($row['type_id']);
        $announcementType->setData('contextId', $row['context_id']);
        $this->getDataObjectSettings('announcement_type_settings', 'type_id', $row['type_id'], $announcementType);

        return $announcementType;
    }

    /**
     * Update the localized settings for this object
     *
     * @param AnnouncementType $announcementType
     */
    public function updateLocaleFields($announcementType)
    {
        $this->updateDataObjectSettings(
            'announcement_type_settings',
            $announcementType,
            ['type_id' => (int) $announcementType->getId()]
        );
    }

    /**
     * Insert a new AnnouncementType.
     *
     * @param AnnouncementType $announcementType
     *
     * @return int
     */
    public function insertObject($announcementType)
    {
        $this->update(
            sprintf('INSERT INTO announcement_types
				(context_id)
				VALUES
				(?)'),
            [
                $announcementType->getContextId()
                    ? (int) $announcementType->getContextId()
                    : null
            ]
        );
        $announcementType->setId($this->getInsertId());
        $this->updateLocaleFields($announcementType);
        return $announcementType->getId();
    }

    /**
     * Update an existing announcement type.
     *
     * @param AnnouncementType $announcementType
     *
     * @return bool
     */
    public function updateObject($announcementType)
    {
        $returner = $this->update(
            'UPDATE	announcement_types
                        SET	context_id = ?
			WHERE	type_id = ?',
            [
                $announcementType->getContextId(),
                (int) $announcementType->getId()
            ]
        );

        $this->updateLocaleFields($announcementType);
        return $returner;
    }

    /**
     * Delete an announcement type. Note that all announcements with this type are also
     * deleted.
     *
     * @param AnnouncementType $announcementType
     */
    public function deleteObject($announcementType)
    {
        return $this->deleteById($announcementType->getId());
    }

    /**
     * Delete an announcement type by announcement type ID. Note that all announcements with
     * this type ID are also deleted.
     */
    public function deleteById(int $typeId): int
    {
        $collector = Repo::announcement()->getCollector()->filterByTypeIds([(int) $typeId]);
        Repo::announcement()->deleteMany($collector);

        return DB::table('announcement_types')
            ->where('type_id', '=', $typeId)
            ->delete();
    }

    /**
     * Delete announcement types by context ID.
     *
     * @param int $contextId
     */
    public function deleteByContextId($contextId)
    {
        foreach ($this->getByContextId($contextId) as $type) {
            $this->deleteObject($type);
        }
    }

    /**
     * Retrieve an array of announcement types matching a particular context ID.
     *
     * @return \Generator<int,AnnouncementType> Matching AnnouncementTypes
     */
    public function getByContextId(?int $contextId)
    {
        if ($contextId) {
            $result = $this->retrieve(
                'SELECT * FROM announcement_types WHERE context_id = ? ORDER BY type_id',
                [$contextId]
            );
        } else {
            $result = $this->retrieve(
                'SELECT * FROM announcement_types WHERE context_id IS NULL ORDER BY type_id'
            );
        }
        foreach ($result as $row) {
            yield $row->type_id => $this->_fromRow((array) $row);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\announcement\AnnouncementTypeDAO', '\AnnouncementTypeDAO');
}
