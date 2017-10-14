<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class NominaE extends TableGateway
{
    private $idnom;
    private $idinom;
    private $idccos;
    private $idconc;
    private $valor;// Cuando se agrega una nueva novedad
    private $dev;
    private $ded;
    private $id;
    private $tipo;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_nomina_e_d', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->idnom   = $datos["idNom"];   
        $this->idinom  = $datos["idInom"];   
        $this->idccos  = $datos["idCcos"];   
        $this->idconc  = $datos["idConc"];   
        $this->valor   = $datos["valor"];   
        $this->id      = $datos["idNov"];
        $this->tipo    = $datos["tipo"];
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(), $valcon, $tipcon, $nombre)
    {
       self::cargaAtributos($data);
       $dev=0;
       $ded=0;
       if ($tipcon==1 ) 
           $dev=$this->valor; 
       else 
           $ded=$this->valor;
       
       if ($valcon==1) // Concepto por horas
       {
          $datos=array
          (
             'idNom'   =>$this->idnom,
             'idINom'  =>$this->idinom,
             'idCcos'  =>$this->idccos,    
             'idConc'  =>$this->idconc, 
             'horas'   =>$this->valor, 
             'detalle' => $nombre
          );
       }else{  // Concepto por valor
          $datos=array
          (
             'idNom'     => $this->idnom,
             'idINom'    => $this->idinom,
             'idCcos'    => $this->idccos,    
             'idConc'    => $this->idconc, 
             'horas'     => 0,  
             'devengado' => $dev,
             'deducido'  => $ded,
             'detalle' => $nombre
          );           
       }  
       $this->insert($datos);
    }
    
    public function edRegistro($data=array())
    {
       self::cargaAtributos($data);
       if ($this->tipo==2)
       {
         $datos=array('horas'=> $this->valor);
       }
       if ($this->tipo==3)
       {
         $datos=array('devengado'=> $this->valor);
       }       
       if ($this->tipo==4)
       {
         $datos=array('deducido'=> $this->valor);
       }              
       $this->update($datos, array('id' => $this->id));
    }
    
    
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
