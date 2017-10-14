<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Gruposconceptosi extends TableGateway
{
    private $id;
    private $idtauto;
    private $idcon;
    private $valor;
    private $idccos;
    private $horasc;
    private $ccosemp;
    private $horcal;
    private $diaslab;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_conceptos_g_i', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->idtauto = $datos["id"];
        $this->idcon   = $datos["tipo"]; 
    }
    
    public function getRegistro($id)
    {
       $id  = (int) $id;
       $datos = $this->select(array('idGcon' => $id));
       return $datos->toArray();
     }     
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $datos=array
       (
           'idGcon'  => $this->idtauto,
           'idCon'    => $this->idcon,
       );
       $this->insert($datos);       
    } 

    public function delRegistro($id)
    {
      $this->delete(array('id' => $id));               
    }
}
?>
