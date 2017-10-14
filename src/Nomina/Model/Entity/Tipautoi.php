<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Tipautoi extends TableGateway
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
        return parent::__construct('n_tip_auto_i', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->idtauto = $datos["id"];
        $this->idcon   = $datos["tipo"]; 
        $this->valor   = $datos["numero"];
        $this->idccos  = $datos["idCencos"];
        $this->horasc  = $datos["horasC"];
        $this->horcal  = $datos["check1"];        
        $this->ccosemp = $datos["check2"];   
        $this->diaslab = $datos["check3"];   
        
    }
    
    public function getRegistro($id)
    {
       $id  = (int) $id;
       $datos = $this->select(array('idTauto' => $id));
       return $datos->toArray();
     }     
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $datos=array
       (
           'valor'    => $this->valor,
           'idTauto'  => $this->idtauto,
           'idCon'    => $this->idcon,
           'idCcos'   => $this->idccos,
           'horasCal' => $this->horcal,
           'cCosEmp'  => $this->ccosemp, 
           'diasLab'  => $this->diaslab,
           'vaca'     => $data['check4'],
       );
       $this->insert($datos);       
    } 

    public function delRegistro($id)
    {
      $this->delete(array('id' => $id));               
    }
}
?>
