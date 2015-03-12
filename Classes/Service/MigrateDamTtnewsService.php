<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/**
 *  Copyright notice
 *
 *  ⓒ 2014 Michiel Roos <michiel@maxserv.nl>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of the
 *  GNU General Public License as published by the Free Software Foundation;
 *  either version 2 of the License, or (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 *  or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Service to Migrate tt_news records enhanced with dam_ttnews
 *
 * @author Michiel Roos <michiel@maxserv.nl>
 */
class MigrateDamTtnewsService extends AbstractService {

    /**
     * @var array
     */
    protected $fieldMapping = array(
        'tx_damnews_dam_images' => 'tx_falttnews_fal_images',
        'tx_damnews_dam_media' => 'tx_falttnews_fal_media'
    );

    /**
     * Main function, returns a FlashMessge
     *
     * @throws \Exception
     *
     * @return \TYPO3\CMS\Core\Messaging\FlashMessage
     */
    public function execute() {
        $this->controller->headerMessage(LocalizationUtility::translate('migrateDamTtnewsCommand', 'dam_falmigration'));
        if (!$this->isTableAvailable('tx_dam_mm_ref')) {
            return $this->getResultMessage('referenceTableNotFound');
        }

        $articlesWithImagesResult = $this->getRecordsWithDamConnections('tt_news', 'tx_damnews_dam_images');
        $this->migrateDamReferencesToFalReferences($articlesWithImagesResult, 'tt_news', 'image', $this->fieldMapping);

        $articlesWithMediaResult = $this->getRecordsWithDamConnections('tt_news', 'tx_damnews_dam_media');
        $this->migrateDamReferencesToFalReferences($articlesWithMediaResult, 'tt_news', 'media', $this->fieldMapping);

        $articlesWithvditeaserResult = $this->getRecordsWithDamConnections('tt_news', 'tx_wmdbvditeaser_damimage');
        $this->migrateDamReferencesToFalReferences($articlesWithvditeaserResult, 'tt_news', 'image');

        $this->updateFalFieldsFromTtNews();

        $this->updateReferenceCounters('tt_news');

        return $this->getResultMessage();
    }

    /**
     * Update sys_file_reference with fields from tt_news (alttext, titletext)
     *
     *
     * @return void
     */
    protected function updateFalFieldsFromTtNews() {
        $rows = $this->database->exec_SELECTgetRows(
            'ref.uid_foreign, ref.uid_local, news.imagealttext, news.imagetitletext',
            'sys_file_reference ref
            INNER JOIN tt_news news
             ON ref.uid_foreign = news.uid',
            "ref.tablenames = 'tt_news' AND (ref.fieldname = 'tx_falttnews_fal_images' OR ref.fieldname = 'tx_falttnews_fal_media')"
        );

        $updateArray = array();

        foreach($rows as $row) {
            // checks for empty values
            if($row['imagetitletext'] !== '' && $row['alternative'] !== '') {
                $updateArray = array(
                    'title' => $row['imagetitletext'],
                    'alternative' => $row['imagealttext']
                );
            }

            if($row['imagetitletext'] === '' && $row['alternative'] !== '') {
                $updateArray = array(
                    'alternative' => $row['imagealttext']
                );
            }

            if($row['imagetitletext'] !== '' && $row['alternative'] === '') {
                $updateArray = array(
                    'title' => $row['imagetitletext']
                );
            }

            if($row['imagetitletext'] === '' && $row['alternative'] === '') {
                continue;
            }


            // update sys_file_reference
            $this->database->exec_UPDATEquery(
                'sys_file_reference',
                'uid_foreign = ' . $row['uid_foreign'],
                $updateArray
            );
        }
    }

}