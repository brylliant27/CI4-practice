<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Schéma en étoile de l'équipe 04 (voir ShemaEtoile_SAE301_equipe4.pdf).
 *
 *   dim_commune ┐
 *   dim_culture ├──► fact_indicateur (au centre)
 *   dim_temps   │
 *   dim_modele  ┘
 *
 * Les séries climatiques brutes (Serie_Climat) ne sont PAS stockées : elles
 * arrivent de l'API et ne servent qu'au calcul (voir App\Libraries\SeriesClimat).
 *
 * Exécution sur une base vide :  php spark migrate
 */
class CreateSchemaEtoile extends Migration
{
    public function up()
    {
        // ---- Dim_Commune ------------------------------------------------------
        $this->forge->addField([
            'id_commune'      => ['type' => 'INT', 'unsigned' => true],
            'nom'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'code_insee'      => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
            'latitude'        => ['type' => 'DECIMAL', 'constraint' => '8,5'],
            'longitude'       => ['type' => 'DECIMAL', 'constraint' => '8,5'],
            'altitude_m'      => ['type' => 'INT', 'null' => true],
            'zone_climatique' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_commune');
        $this->forge->createTable('dim_commune');

        // ---- Dim_Culture (seuils physiologiques, référentiel du pôle DATA) ------
        $this->forge->addField([
            'id_culture'               => ['type' => 'INT', 'unsigned' => true],
            'nom'                      => ['type' => 'VARCHAR', 'constraint' => 100],
            't_base_c'                 => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'gdd_maturite_cj'          => ['type' => 'DECIMAL', 'constraint' => '7,1', 'null' => true],
            'seuil_gel_c'              => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'seuil_stress_thermique_c' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            't_opt_min_c'              => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            't_opt_max_c'              => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'besoin_eau_mm'            => ['type' => 'DECIMAL', 'constraint' => '7,1', 'null' => true],
            'source'                   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_culture');
        $this->forge->createTable('dim_culture');

        // ---- Dim_Temps (horizons comparés) -------------------------------------
        $this->forge->addField([
            'id_temps'     => ['type' => 'INT', 'unsigned' => true],
            'libelle'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'annee_debut'  => ['type' => 'SMALLINT'],
            'annee_fin'    => ['type' => 'SMALLINT'],
            'type_periode' => ['type' => 'VARCHAR', 'constraint' => 20], // 'reference' ou 'projection'
        ]);
        $this->forge->addPrimaryKey('id_temps');
        $this->forge->createTable('dim_temps');

        // ---- Dim_Modele (modèles climatiques Open-Meteo) ------------------------
        $this->forge->addField([
            'id_modele'     => ['type' => 'INT', 'unsigned' => true],
            'code'          => ['type' => 'VARCHAR', 'constraint' => 50],
            'libelle'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'institut'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'resolution_km' => ['type' => 'SMALLINT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_modele');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('dim_modele');

        // ---- Fact_Indicateur (au centre de l'étoile) ---------------------------
        // Une ligne = la valeur d'UN indicateur (moyenne annuelle sur l'horizon)
        // pour une session, une commune, une culture, un horizon et un modèle.
        $this->forge->addField([
            'id_session'      => ['type' => 'VARCHAR', 'constraint' => 32],
            'id_commune'      => ['type' => 'INT', 'unsigned' => true],
            'id_culture'      => ['type' => 'INT', 'unsigned' => true],
            'id_temps'        => ['type' => 'INT', 'unsigned' => true],
            'id_modele'       => ['type' => 'INT', 'unsigned' => true],
            'code_indicateur' => ['type' => 'VARCHAR', 'constraint' => 30],
            'valeur'          => ['type' => 'DOUBLE', 'null' => true],
            'date_calcul'     => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey(['id_session', 'id_commune', 'id_culture', 'id_temps', 'id_modele', 'code_indicateur']);
        $this->forge->addKey('date_calcul'); // purge RGPD des lignes de plus de 30 jours
        $this->forge->addForeignKey('id_commune', 'dim_commune', 'id_commune');
        $this->forge->addForeignKey('id_culture', 'dim_culture', 'id_culture');
        $this->forge->addForeignKey('id_temps', 'dim_temps', 'id_temps');
        $this->forge->addForeignKey('id_modele', 'dim_modele', 'id_modele');
        $this->forge->createTable('fact_indicateur');
    }

    public function down()
    {
        $this->forge->dropTable('fact_indicateur', true);
        $this->forge->dropTable('dim_modele', true);
        $this->forge->dropTable('dim_temps', true);
        $this->forge->dropTable('dim_culture', true);
        $this->forge->dropTable('dim_commune', true);
    }
}
