<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Empleados extends TableGateway
{
    private $id;
    private $cedula;
    private $nombre;
    private $apellido; 
    private $dir; 
    private $numero; // Telefono
    private $SexEmp; 
    private $FecNac; // Fecha de nacimiento
    private $email;  
    
    // Fondos
    private $idsal; 
    private $idpen; 
    private $idces;              
    private $idarp; 
    private $idcaja; 
    private $idfav;              
    private $idfafc;              
    // Contractuales
    private $idcar; 
    private $idcencos; 
    private $idgrupo; 
    private $idcal;                     
    private $idtau;                              
    private $idtau2;                              
    private $idtau3;  
    private $idtau4;  
    private $idprej;                              
    private $tipo;       
    private $idtemp;
    private $foto;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id        = $datos["id"];    
        $this->cedula    = $datos["cedula"];   
        $this->nombre    = $datos["nombre"];     
        $this->apellido  = $datos["apellido1"];    
        $this->dir       = $datos["dir"];    
        $this->numero    = $datos["numero"];    // Telefono
        $this->SexEmp    = $datos["sexo"];      
        $this->FecNac    = $datos["fecDoc"];    // Fecha de nacimiento
        $this->email     = $datos["email"];
        // Fondos
        $this->idsal     = $datos["idSal"];    
        $this->idpen     = $datos["idPen"];    
        $this->idces     = $datos["idCes"];                 
        $this->idarp     = $datos["idArp"];    
        $this->idcaja    = $datos["idCaja"];    
        $this->idfav     = $datos["idFav"];                 
        $this->idfafc    = $datos["idFafc"];                 
        // Contractuales
        $this->idcar     = $datos["idCar"];    
        $this->idcencos  = $datos["idCencos"];    
        $this->idgrupo   = $datos["idGrupo"];    
        $this->idtau     = $datos["idTau"];                                 
        $this->idtau2    = $datos["idTau2"];                                 
        $this->idtau3    = $datos["idTau3"];                                 
        $this->idtau4    = $datos["idTau4"];                                 
        $this->idprej    = $datos["idPrej"];                                 
        $this->tipo      = $datos["tipoC"]; 
        $this->idtemp    = $datos["idTemp"];
        
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(), $sueldo)
    {
       self::cargaAtributos($data);
       $id = $this->id;
       $idBancoP = $data['idBancoP'];
       if ( $idBancoP == 0 )
            $idBancoP = $data['idBanco'];
       $datos=array
       (
           "CedEmp"   => $this->cedula,   
           "nombre"   => $this->nombre,   
           "apellido" => $this->apellido,    
           "DirEmp"  => $this->dir,    
           "TelEmp"  => $this->numero,    // Telefono
           "idFsal"  => $this->idsal,    
           "idFpen"  => $this->idpen,    
           "idFces"  => $this->idces,                 
           "idFarp"  => $this->idarp,    
           "idcaja"  => $this->idcaja,    
           "idFav"   => $this->idfav,                 
           "idFafc"  => $this->idfafc,                 
           "idCar"   => $this->idcar,    
           "idCcos"  => $this->idcencos,    
           "idGrup"  => $this->idgrupo,    
           "idTau"   => $this->idtau,         
           "idTau2"  => $this->idtau2,         
           "idTau3"  => $this->idtau3,         
           "idTau4"  => $this->idtau4,         
           "idPref"  => $this->idprej,                                 
           "IdTcon"  => $this->tipo,// Tipo de contrato                                                  
           "IdTemp"  => $this->idtemp,
           "SexEmp"  => $this->SexEmp,      
           "FecNac"  => $this->FecNac,      
           "email"   => $this->email,      
           "estatura"  => $data['estatura'],       // Aspectos fisicos
           "sangre"    => $data['sangre'],                  
           "alergias"  => $data['alergias'], 
           "operaciones"  => $data['operaciones'], 
           "enfermedades" => $data['enfermedades'],            
           "limitacion"   => $data['limitacion'],                       
           "fuma"       => $data['fuma'], 
           "bebe"       => $data['bebe'], 
           "lentes"     => $data['lentes'],                       
           "clubSocial" => $data['clubSocial'], // Aficiones y gustos
           "deportes"   => $data['deportes'],                                  
           "libros"     => $data['libros'],                                  
           "musica"     => $data['musica'],                                  
           "otrasAct"    => $data['otrasAct'],                                             
           "formaPago"     => $data['formaPago'],                                  
           "idBanco"     => $data['idBanco'],                                  
           "idBancoPlano"     => $idBancoP,// Banco plano                                 
           "numCuenta"    => $data['numCuenta'], // Numero de cuenta                                                       
           "tipCuenta"   => $data['tipo1'],            
           "estado"    => $data['estado'],    
           "activo"    => $data['estado'],    
           "idSal"    => $data['idSalario'],    
           "idCiu"    => $data['idCiu'],    
           "idRies"    => $data['idTar'],    
           "pensionado" => $data['check1'],  // Pensionado             
           "sueldo"    => $sueldo,    
           "fecIng"    => $data['fecIng'],    
           "variable" => $data['check2'],  // Variable             
           "regimen" => $data['check3'],  // Regimen anterior                        
           "integral" => $data['check4'],  // Integral         
        );
       if ($id==0) // Nuevo registro
          $this->insert($datos);
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
