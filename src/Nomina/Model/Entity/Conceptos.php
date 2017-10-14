<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Conceptos extends TableGateway
{
    private $id;
    private $nombre;
    private $tipo;
    private $idfor;
    private $auto;
    private $alias;
    private $valor;
    private $per;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_conceptos', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id     = $datos["id"];    
        $this->nombre = $datos["nombre"]; 
        $this->tipo   = $datos["tipo"]; 
        $this->idfor  = $datos["idFor"]; 
        $this->auto   = $datos["check1"]; // Define si es automatico o no el concepto
        $this->alias  = $datos["alias"]; 
        $this->valor  = $datos["tipo2"]; 
        $this->per    = $datos["periodo"]; 
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       $datos=array
       (
           'nombre' =>$this->nombre,
           'tipo'   =>$this->tipo,
           'idFor'  =>$this->idfor,
           'auto'   =>$this->auto,
           'alias'  =>$this->alias,
           'codigo' =>$data['codigo'],
           'valor'  =>$this->valor,
           'perAuto'=>$this->per,
           'fondo'  =>$data['tipo3'],           
           'info'   =>$data['check2'],     
           'tercero' =>$data['check4'],     
           'retroConv' =>$data['check5'],                
           'idConcRetro' =>$data['tipo4'],     
           'codCta' =>$data['codCta'],     
           'natCta' =>$data['natCta'],     
           'idTer'  =>$data['idTer'],                
           'nitFon'  =>$data['conFis'], 
           'activo'  =>$data['estado'],  
           'idConcHere'=>$data['tipo5'],                          
           
        );
       if ($id==0) // Nuevo registro
       { 
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;          
       }
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
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
