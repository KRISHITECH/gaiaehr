<?php

/**
 * 2.53	Physical Exam Section (V2)
 *
 * The section includes direct observations made by a clinician. The examination may include the use of simple
 * instruments and may also describe simple maneuvers performed directly on the patient’s body.
 *
 * It also includes observations made by the examining clinician using only inspection, palpation, auscultation,
 * and percussion. It does not include laboratory or imaging findings.
 *
 * The exam may be limited to pertinent body systems based on the patient’s chief complaint or it may include a
 * comprehensive examination. The examination may be reported as a collection of random clinical statements or
 * it may be reported categorically.
 *
 * The Physical Exam section may contain multiple nested subsections; Vital Signs, General Status, and those
 * listed in the Additional Physical Examination Subsections appendix.
 *
 * Contains:
 * Wound Class Observation
 *
 */

namespace LevelSection;

use LevelEntry;
use Exception;

class physicalExam
{

    /**
     * @param $Data
     */
    private static function Validate($Data)
    {

    }

    /**
     * Build the Narrative part of this section
     * @param $Data
     */
    public static function Narrative($Data)
    {

    }

    /**
     * @param $PortionData
     * @param $CompleteData
     * @return array|Exception
     */
    public static function Insert($PortionData, $CompleteData)
    {
        try
        {
            // Validate first
            self::Validate($PortionData);

            $Section = [
                'component' => [
                    'section' => [
                        'templateId' => [
                            '@attributes' => [
                                'root' => '2.16.840.1.113883.10.20.2.10.2',
                                'extension' => $PortionData['PhysicalExam']['date']
                            ]
                        ],
                        'code' => [
                            '@attributes' => [
                                'code' => '29545-1',
                                'codeSystem' => '2.16.840.1.113883.6.1',
                                'codeSystemName' => 'LOINC',
                                'displayName' => 'Physical Findings'
                            ]
                        ],
                        'title' => 'Physical Findings',
                        'text' => self::Narrative($PortionData['PhysicalExam'])
                    ]
                ]
            ];

            // 3.110 Wound Class Observation [0..*]
            foreach($PortionData['PhysicalExam']['WoundsClassObservation'] as $Observation) {
                $Section['component']['section']['entry'][] = [
                    '@attributes' => [
                        'typeCode' => 'DRIV'
                    ],
                    'observation' => LevelEntry\woundClassObservation::Insert($Observation, $CompleteData)
                ];
            }

            return $Section;
        }
        catch (Exception $Error)
        {
            return $Error;
        }
    }

}